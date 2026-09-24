<?php
declare(strict_types=1);

use App\Database;
use App\Repositories\ScamRepository;
use App\Services\OpenAIService;
use App\Services\RetentionService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
$db = Database::connect();
$lockPath = BASE_PATH . '/storage/cache/daily-cron.lock';
$lockHandle = fopen($lockPath, 'c+');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Cron draait al; afgebroken.\n");
    exit(0);
}

$jobId = 0;
$stats = ['sources' => 0, 'items' => 0, 'duplicates' => 0, 'reviews' => 0, 'failures' => 0];
$started = new DateTimeImmutable();
try {
    $run = $db->prepare("INSERT INTO cron_runs (job_name, lock_key, started_at, status) VALUES ('daily-source-import', 'daily-source-import', NOW(), 'running')");
    $run->execute();
    $jobId = (int) $db->lastInsertId();
    $sources = $db->query("SELECT * FROM sources WHERE active = 1 AND trust_status = 'verified' ORDER BY id")->fetchAll();
    $repository = new ScamRepository($db);
    $ai = new OpenAIService();
    foreach ($sources as $source) {
        $stats['sources']++;
        $feed = trim((string) ($source['feed_url'] ?? ''));
        if ($feed === '') {
            markSourceSuccess($db, (int) $source['id']);
            continue;
        }
        try {
            $body = fetchTrustedUrl($feed);
            foreach (parseFeed($body) as $item) {
                $content = trim(($item['title'] ?? '') . "\n" . ($item['content'] ?? ''));
                $hash = hash('sha256', $content);
                $duplicate = $db->prepare('SELECT id FROM source_items WHERE source_id = :source_id AND (url = :url OR content_hash = :content_hash OR (external_id IS NOT NULL AND external_id = :external_id)) LIMIT 1');
                $duplicate->execute(['source_id' => $source['id'], 'url' => $item['url'], 'content_hash' => $hash, 'external_id' => $item['external_id']]);
                if ($duplicate->fetchColumn() !== false) {
                    $stats['duplicates']++;
                    continue;
                }
                $insert = $db->prepare("INSERT INTO source_items (source_id, external_id, url, title, raw_content, normalized_content, content_hash, published_at, status)
                    VALUES (:source_id, :external_id, :url, :title, :raw_content, :normalized_content, :content_hash, :published_at, 'new')");
                $insert->execute([
                    'source_id' => $source['id'],
                    'external_id' => $item['external_id'],
                    'url' => $item['url'],
                    'title' => mb_substr($item['title'], 0, 255),
                    'raw_content' => $item['content'],
                    'normalized_content' => $repository->normalize($content),
                    'content_hash' => $hash,
                    'published_at' => $item['published_at'],
                ]);
                $sourceItemId = (int) $db->lastInsertId();
                $stats['items']++;
                $candidates = $repository->matchCandidates($content);
                $analysis = $ai->classify(['title' => $item['title'], 'content' => $item['content'], 'url' => $item['url']], $candidates);
                if ($analysis !== null) {
                    $analysisInsert = $db->prepare("INSERT INTO ai_analyses (source_item_id, model, prompt_version, input_hash, classification, output_json, status)
                        VALUES (:source_item_id, :model, 'v1', :input_hash, :classification, :output_json, 'suggestion')");
                    $analysisInsert->execute([
                        'source_item_id' => $sourceItemId,
                        'model' => (string) env('OPENAI_MODEL', 'gpt-4o-mini'),
                        'input_hash' => $hash,
                        'classification' => $analysis['classification'],
                        'output_json' => json_text($analysis),
                    ]);
                    $analysisId = (int) $db->lastInsertId();
                    $review = $db->prepare("INSERT INTO review_queue (source_item_id, ai_analysis_id, item_type, priority, status, suggested_action) VALUES (:source_item_id, :ai_id, 'source_item', :priority, 'pending', :suggested_action)");
                    $review->execute(['source_item_id' => $sourceItemId, 'ai_id' => $analysisId, 'priority' => $analysis['classification'] === 'irrelevant' ? 1 : 5, 'suggested_action' => (string) ($analysis['recommended_editorial_action'] ?? 'Beoordeel bronitem')]);
                    $stats['reviews']++;
                } else {
                    $review = $db->prepare("INSERT INTO review_queue (source_item_id, item_type, priority, status, suggested_action) VALUES (:source_item_id, 'source_item', 3, 'pending', 'Beoordeel nieuw bronitem')");
                    $review->execute(['source_item_id' => $sourceItemId]);
                    $stats['reviews']++;
                }
            }
            markSourceSuccess($db, (int) $source['id']);
        } catch (Throwable $exception) {
            $stats['failures']++;
            markSourceFailure($db, (int) $source['id'], $exception->getMessage());
        }
    }
    $purged = (new RetentionService($db))->purge();
    $stats['purged'] = array_sum($purged);
    $summary = sprintf('%d bronnen gecontroleerd, %d nieuwe items, %d duplicaten, %d review-items, %d fouten', $stats['sources'], $stats['items'], $stats['duplicates'], $stats['reviews'], $stats['failures']);
    $finish = $db->prepare("UPDATE cron_runs SET finished_at = NOW(), status = :status, summary = :summary, stats_json = :stats WHERE id = :id");
    $finish->execute(['status' => $stats['failures'] > 0 ? 'partial' : 'success', 'summary' => $summary, 'stats' => json_text($stats), 'id' => $jobId]);
    echo $summary . "\n";
} catch (Throwable $exception) {
    if ($jobId > 0) {
        $finish = $db->prepare("UPDATE cron_runs SET finished_at = NOW(), status = 'failed', error_message = :error_message, stats_json = :stats WHERE id = :id");
        $finish->execute(['error_message' => $exception->getMessage(), 'stats' => json_text($stats), 'id' => $jobId]);
    }
    file_put_contents(BASE_PATH . '/storage/logs/cron.log', '[' . date('c') . '] ' . $exception->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

/** @return string */
function fetchTrustedUrl(string $url): string
{
    if (!preg_match('#^https://#i', $url)) {
        throw new RuntimeException('Alleen HTTPS-feeds zijn toegestaan.');
    }
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Kan HTTP-client niet initialiseren.');
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25, CURLOPT_USERAGENT => 'ScamSpotterBot/1.0 (+https://scamspotter.nl/werkwijze)']);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
        throw new RuntimeException('Feed ophalen mislukt: HTTP ' . $status . ' ' . $error);
    }
    if (strlen($body) > 5_000_000) {
        throw new RuntimeException('Feed is groter dan 5 MB.');
    }
    return (string) $body;
}

/** @return list<array{external_id: string, url: string, title: string, content: string, published_at: ?string}> */
function parseFeed(string $body): array
{
    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('SimpleXML is niet geïnstalleerd.');
    }
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);
    if ($xml === false) {
        throw new RuntimeException('Ongeldige RSS/XML-feed.');
    }
    $items = [];
    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $items[] = [
                'external_id' => (string) ($item->guid ?: $item->link),
                'url' => (string) $item->link,
                'title' => trim((string) $item->title),
                'content' => trim((string) ($item->description ?: $item->children('content', true)->encoded)),
                'published_at' => dateFromFeed((string) ($item->pubDate ?? '')),
            ];
        }
    } elseif (isset($xml->entry)) {
        foreach ($xml->entry as $entry) {
            $items[] = [
                'external_id' => (string) $entry->id,
                'url' => (string) ($entry->link['href'] ?? ''),
                'title' => trim((string) $entry->title),
                'content' => trim((string) $entry->content),
                'published_at' => dateFromFeed((string) ($entry->published ?? $entry->updated ?? '')),
            ];
        }
    }
    return array_values(array_filter($items, static fn (array $item): bool => $item['title'] !== '' && filter_var($item['url'], FILTER_VALIDATE_URL) !== false));
}

function dateFromFeed(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function markSourceSuccess(PDO $db, int $sourceId): void
{
    $statement = $db->prepare('UPDATE sources SET last_success_at = NOW(), last_error_at = NULL, last_error = NULL WHERE id = :id');
    $statement->execute(['id' => $sourceId]);
}

function markSourceFailure(PDO $db, int $sourceId, string $message): void
{
    $statement = $db->prepare('UPDATE sources SET last_error_at = NOW(), last_error = :error WHERE id = :id');
    $statement->execute(['id' => $sourceId, 'error' => mb_substr($message, 0, 1000)]);
}
