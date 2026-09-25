<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ScamRepository;
use DateTimeImmutable;
use PDO;
use Throwable;

final class ReviewPublicationService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<int> */
    public function publishApprovedBatch(): array
    {
        $statement = $this->db->query("SELECT id FROM review_queue WHERE status = 'approved' ORDER BY reviewed_at, id");
        $publishedAlertIds = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $reviewId) {
            $alertId = $this->publishApproved((int) $reviewId);
            if ($alertId !== null) {
                $publishedAlertIds[] = $alertId;
            }
        }
        return $publishedAlertIds;
    }

    public function publishApproved(int $reviewId): ?int
    {
        $review = $this->approvedReview($reviewId);
        if ($review === null) {
            return null;
        }

        $this->db->beginTransaction();
        try {
            $existingAlertId = $this->alertForSourceItem((int) $review['source_item_id']);
            if ($existingAlertId !== null) {
                $this->markProcessed($review);
                $this->db->commit();
                return $existingAlertId;
            }

            $analysis = $this->analysis($review['output_json'] ?? null);
            $classification = trim((string) ($review['classification'] ?? ''));
            if ($classification === '') {
                $classification = 'new_alert';
            }

            $variantId = $this->resolveVariant($analysis, (string) $review['title'] . "\n" . (string) $review['raw_content']);
            if (str_starts_with((string) ($review['external_id'] ?? ''), 'seed-') && $variantId !== null) {
                $seedAlertId = $this->publishedAlertForVariant($variantId);
                if ($seedAlertId !== null) {
                    $this->linkSource($review, $seedAlertId, (string) $review['title']);
                    $this->markProcessed($review);
                    $this->db->commit();
                    return $seedAlertId;
                }
            }

            if (!in_array($classification, ['new_alert', 'existing_scam', 'update_existing'], true)) {
                $this->markProcessed($review);
                $this->db->commit();
                return null;
            }

            $title = $this->cleanTitle((string) $review['title'], (string) ($review['source_organization'] ?? ''));
            $content = $this->cleanContent((string) ($review['raw_content'] ?? ''));
            $summary = $this->summary($content, (string) ($review['source_organization'] ?? ''));
            $body = $content !== '' ? $content : $summary;
            $slug = $this->uniqueSlug($title, (int) $review['source_item_id']);
            $publishedAt = $this->publishedAt((string) ($review['published_at'] ?? ''));

            $insert = $this->db->prepare("INSERT INTO scam_alerts
                (variant_id, source_id, title, slug, summary, body, event_date, status, published_at, last_reviewed_at)
                VALUES (:variant_id, :source_id, :title, :slug, :summary, :body, :event_date, 'published', :published_at, NOW())");
            $insert->execute([
                'variant_id' => $variantId,
                'source_id' => (int) $review['source_id'],
                'title' => $title,
                'slug' => $slug,
                'summary' => $summary,
                'body' => $body,
                'event_date' => $publishedAt !== null ? substr($publishedAt, 0, 10) : null,
                'published_at' => $publishedAt ?? date('Y-m-d H:i:s'),
            ]);
            $alertId = (int) $this->db->lastInsertId();

            $this->linkSource($review, $alertId, $title);
            $this->markProcessed($review);
            $this->db->commit();
            return $alertId;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    private function approvedReview(int $reviewId): ?array
    {
        $statement = $this->db->prepare("SELECT rq.source_item_id, rq.ai_analysis_id,
                si.source_id, si.external_id, si.url, si.title, si.raw_content, si.published_at,
                ai.classification, ai.output_json, s.organization AS source_organization
            FROM review_queue rq
            INNER JOIN source_items si ON si.id = rq.source_item_id
            LEFT JOIN ai_analyses ai ON ai.id = rq.ai_analysis_id
            INNER JOIN sources s ON s.id = si.source_id
            WHERE rq.id = :id AND rq.status = 'approved'
            LIMIT 1");
        $statement->execute(['id' => $reviewId]);
        $review = $statement->fetch();
        return is_array($review) ? $review : null;
    }

    private function alertForSourceItem(int $sourceItemId): ?int
    {
        $statement = $this->db->prepare("SELECT entity_id FROM source_links
            WHERE source_item_id = :source_item_id AND entity_type = 'alert'
            ORDER BY id DESC LIMIT 1");
        $statement->execute(['source_item_id' => $sourceItemId]);
        $alertId = $statement->fetchColumn();
        return $alertId === false ? null : (int) $alertId;
    }

    private function publishedAlertForVariant(int $variantId): ?int
    {
        $statement = $this->db->prepare("SELECT id FROM scam_alerts
            WHERE variant_id = :variant_id AND status = 'published'
            ORDER BY id DESC LIMIT 1");
        $statement->execute(['variant_id' => $variantId]);
        $alertId = $statement->fetchColumn();
        return $alertId === false ? null : (int) $alertId;
    }

    /** @return array<string, mixed> */
    private function analysis(mixed $json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $analysis */
    private function resolveVariant(array $analysis, string $text): ?int
    {
        $suggested = trim((string) ($analysis['variant'] ?? ''));
        if ($suggested !== '') {
            $slug = $this->slugify($suggested);
            $statement = $this->db->prepare("SELECT id FROM scam_variants
                WHERE status = 'published' AND (slug = :slug OR LOWER(name) = LOWER(:name)) LIMIT 1");
            $statement->execute(['slug' => $slug, 'name' => $suggested]);
            $variantId = $statement->fetchColumn();
            if ($variantId !== false) {
                return (int) $variantId;
            }
        }

        $candidates = (new ScamRepository($this->db))->matchCandidates($text);
        return isset($candidates[0]['variant']['id']) ? (int) $candidates[0]['variant']['id'] : null;
    }

    private function cleanTitle(string $title, string $organization): string
    {
        $title = trim(strip_tags($title));
        if ($organization !== '') {
            $withoutPrefix = preg_replace('/^' . preg_quote($organization, '/') . '\s*:\s*/iu', '', $title);
            if (is_string($withoutPrefix) && $withoutPrefix !== '') {
                $title = trim($withoutPrefix);
            }
        }
        return mb_substr($title !== '' ? $title : 'Actuele scamwaarschuwing', 0, 255);
    }

    private function cleanContent(string $content): string
    {
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/[ \t]+/u', ' ', $content);
        $content = preg_replace('/\R{3,}/u', "\n\n", (string) $content);
        return trim(is_string($content) ? $content : '');
    }

    private function summary(string $content, string $organization): string
    {
        $summary = preg_replace('/\s+/u', ' ', $content);
        $summary = trim(is_string($summary) ? $summary : '');
        if ($summary === '') {
            $summary = 'Actuele waarschuwing van ' . ($organization !== '' ? $organization : 'een betrouwbare bron') . '.';
        }
        if (mb_strlen($summary) > 500) {
            $summary = rtrim(mb_substr($summary, 0, 497)) . '…';
        }
        return $summary;
    }

    private function uniqueSlug(string $title, int $sourceItemId): string
    {
        $base = $this->slugify($title);
        $base = mb_substr($base !== '' ? $base : 'waarschuwing-' . $sourceItemId, 0, 170);
        $slug = $base;
        $suffix = 2;
        while (true) {
            $statement = $this->db->prepare('SELECT id FROM scam_alerts WHERE slug = :slug LIMIT 1');
            $statement->execute(['slug' => $slug]);
            if ($statement->fetchColumn() === false) {
                return $slug;
            }
            $slug = mb_substr($base, 0, 165) . '-' . $suffix++;
        }
    }

    private function slugify(string $value): string
    {
        $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : $value;
        $ascii = is_string($ascii) && $ascii !== '' ? $ascii : $value;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($ascii));
        return trim(is_string($slug) ? $slug : '', '-');
    }

    private function publishedAt(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $review */
    private function linkSource(array $review, int $alertId, string $label): void
    {
        $statement = $this->db->prepare("INSERT INTO source_links
            (source_id, source_item_id, entity_type, entity_id, label, published_at, checked_at)
            VALUES (:source_id, :source_item_id, 'alert', :entity_id, :label, :published_at, NOW())");
        $statement->execute([
            'source_id' => (int) $review['source_id'],
            'source_item_id' => (int) $review['source_item_id'],
            'entity_id' => $alertId,
            'label' => mb_substr($label, 0, 255),
            'published_at' => $this->publishedAt((string) ($review['published_at'] ?? '')),
        ]);
    }

    /** @param array<string, mixed> $review */
    private function markProcessed(array $review): void
    {
        $sourceItem = $this->db->prepare("UPDATE source_items SET status = 'reviewed' WHERE id = :id");
        $sourceItem->execute(['id' => (int) $review['source_item_id']]);
        if ((int) ($review['ai_analysis_id'] ?? 0) > 0) {
            $analysis = $this->db->prepare("UPDATE ai_analyses SET status = 'accepted' WHERE id = :id");
            $analysis->execute(['id' => (int) $review['ai_analysis_id']]);
        }
    }
}
