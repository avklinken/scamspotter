<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ScamRepository;
use App\Services\BusinessAuthService;
use App\Services\OpenAIService;
use App\Services\OrganizationCheckService;
use App\Services\RateLimitService;
use App\Services\ScamAnalysisOrchestrator;
use App\Services\ScamAnalysisService;

final class BusinessApiController extends Controller
{
    public function session(Request $request): never
    {
        $user = business_user();
        Response::json(['authenticated' => $user !== null, 'user' => $user, 'csrf' => $user !== null ? csrf_token() : null]);
    }

    public function check(Request $request): never
    {
        [$organizationId, $userId, $sessionAuth] = $this->authenticate($request);
        $this->limit($request, $organizationId, $userId, 'check');
        $data = $request->json();
        if ($sessionAuth && !hash_equals(csrf_token(), (string) ($request->header('X-CSRF-Token', '') ?? ''))) {
            Response::json(['error' => 'csrf_failed'], 419);
        }
        try {
            $result = $this->service()->run($organizationId, $userId, $data);
            Response::json(['ok' => true, 'result' => $this->publicResult($result)]);
        } catch (\InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            file_put_contents(BASE_PATH . '/storage/logs/app.log', '[' . date('c') . '] business_api_check ' . $exception->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            Response::json(['error' => 'analysis_failed'], 500);
        }
    }

    public function analyse(Request $request): never
    {
        [$organizationId, $userId, $sessionAuth] = $this->authenticate($request);
        $this->limit($request, $organizationId, $userId, 'check');
        if ($sessionAuth && !hash_equals(csrf_token(), (string) ($request->header('X-CSRF-Token', '') ?? ''))) {
            Response::json(['error' => 'csrf_failed'], 419);
        }
        $data = $request->json();
        $data['input_type'] = $data['input_type'] ?? $data['content_type'] ?? 'message';
        $data['body'] = $data['body'] ?? $data['content'] ?? $data['input'] ?? '';
        try {
            $result = $this->service()->run($organizationId, $userId, $data);
            Response::json(['ok' => true, 'result' => $this->publicResult($result)]);
        } catch (\InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function usage(Request $request): never
    {
        [$organizationId] = $this->authenticate($request);
        $statement = $this->db()->prepare("SELECT COUNT(*) AS runs, COALESCE(SUM(input_tokens), 0) AS input_tokens,
                COALESCE(SUM(output_tokens), 0) AS output_tokens
            FROM usage_events WHERE organization_id = :organization_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $statement->execute(['organization_id' => $organizationId]);
        Response::json(['ok' => true, 'period' => '30d', 'usage' => $statement->fetch() ?: ['runs' => 0, 'input_tokens' => 0, 'output_tokens' => 0]]);
    }

    public function intelligence(Request $request): never
    {
        if ($request->bearerToken() === null) {
            Response::json(['error' => 'api_key_required'], 401);
        }
        [$organizationId, $userId] = $this->authenticate($request);
        $this->limit($request, $organizationId, $userId, 'intelligence');
        $since = (string) $request->query('since', '');
        try {
            $sinceDate = $since !== '' ? (new \DateTimeImmutable($since))->format('Y-m-d H:i:s') : (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            Response::json(['error' => 'invalid_since'], 422);
        }
        $limit = max(1, min(100, (int) $request->query('limit', '50')));
        $statement = $this->db()->prepare("SELECT a.id, a.title, a.slug, a.summary, a.event_date, a.published_at,
                v.id AS variant_id, v.name AS variant_name, v.slug AS variant_slug,
                t.name AS type_name, t.slug AS type_slug, f.name AS family_name, f.slug AS family_slug,
                s.organization AS source_organization, s.homepage AS source_homepage
            FROM scam_alerts a
            LEFT JOIN scam_variants v ON v.id = a.variant_id
            LEFT JOIN scam_types t ON t.id = v.type_id
            LEFT JOIN scam_families f ON f.id = t.family_id
            LEFT JOIN sources s ON s.id = a.source_id
            WHERE a.status = 'published' AND COALESCE(a.published_at, a.created_at) >= :since
            ORDER BY COALESCE(a.published_at, a.created_at) DESC LIMIT {$limit}");
        $statement->execute(['since' => $sinceDate]);
        $alerts = $statement->fetchAll();
        $variantIds = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['variant_id'] ?? 0), $alerts)));
        $indicators = [];
        if ($variantIds !== []) {
            $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
            $indicatorQuery = $this->db()->prepare("SELECT vi.variant_id, i.indicator_type, i.value, i.normalized_value, i.explanation, COALESCE(vi.weight_override, i.weight) AS weight
                FROM scam_variant_indicators vi INNER JOIN scam_indicators i ON i.id = vi.indicator_id
                WHERE vi.variant_id IN ({$placeholders}) AND i.status = 'active' ORDER BY weight DESC");
            $indicatorQuery->execute($variantIds);
            foreach ($indicatorQuery->fetchAll() as $indicator) {
                $indicators[(int) $indicator['variant_id']][] = [
                    'type' => $indicator['indicator_type'],
                    'value' => $indicator['value'],
                    'normalized_value' => $indicator['normalized_value'],
                    'explanation' => $indicator['explanation'],
                    'weight' => (float) $indicator['weight'],
                ];
            }
        }
        $items = array_map(static function (array $alert) use ($indicators): array {
            $variantId = (int) ($alert['variant_id'] ?? 0);
            return [
                'id' => (int) $alert['id'],
                'title' => $alert['title'],
                'slug' => $alert['slug'],
                'summary' => $alert['summary'],
                'event_date' => $alert['event_date'],
                'published_at' => $alert['published_at'],
                'url' => url('/waarschuwingen/' . $alert['slug']),
                'family' => ['name' => $alert['family_name'], 'slug' => $alert['family_slug']],
                'type' => ['name' => $alert['type_name'], 'slug' => $alert['type_slug']],
                'variant' => ['id' => $variantId, 'name' => $alert['variant_name'], 'slug' => $alert['variant_slug']],
                'indicators' => array_slice($indicators[$variantId] ?? [], 0, 30),
                'source' => ['organization' => $alert['source_organization'], 'homepage' => $alert['source_homepage']],
            ];
        }, $alerts);
        Response::json(['ok' => true, 'since' => $sinceDate, 'items' => $items]);
    }

    public function report(Request $request): never
    {
        [$organizationId, $userId, $sessionAuth] = $this->authenticate($request);
        $this->limit($request, $organizationId, $userId, 'report');
        if ($sessionAuth && !hash_equals(csrf_token(), (string) ($request->header('X-CSRF-Token', '') ?? ''))) {
            Response::json(['error' => 'csrf_failed'], 419);
        }
        $data = $request->json();
        try {
            $id = $this->service()->report($organizationId, $userId, (int) ($data['check_id'] ?? 0), (string) ($data['description'] ?? ''));
            Response::json(['ok' => true, 'report_id' => $id], 201);
        } catch (\InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function feedback(Request $request): never
    {
        [$organizationId, $userId, $sessionAuth] = $this->authenticate($request);
        $this->limit($request, $organizationId, $userId, 'feedback');
        if ($sessionAuth && !hash_equals(csrf_token(), (string) ($request->header('X-CSRF-Token', '') ?? ''))) {
            Response::json(['error' => 'csrf_failed'], 419);
        }
        $data = $request->json();
        try {
            $this->service()->feedback(
                $organizationId,
                $userId,
                (int) ($data['check_id'] ?? 0),
                (string) ($data['feedback'] ?? ''),
                (string) ($data['comment'] ?? ''),
            );
            Response::json(['ok' => true], 201);
        } catch (\InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /** @return array{0: int, 1: ?int, 2: bool} */
    private function authenticate(Request $request): array
    {
        $user = business_user();
        if (is_array($user)) {
            return [(int) $user['organization_id'], (int) $user['id'], true];
        }
        $key = $request->bearerToken();
        $record = $key !== null ? (new BusinessAuthService($this->db()))->authenticateApiKey($key) : null;
        if (!is_array($record)) {
            Response::json(['error' => 'authentication_required'], 401);
        }
        return [(int) $record['organization_id'], null, false];
    }

    private function service(): OrganizationCheckService
    {
        $local = new ScamAnalysisService(new ScamRepository($this->db()));
        return new OrganizationCheckService($this->db(), new ScamAnalysisOrchestrator($local, new OpenAIService()));
    }

    private function limit(Request $request, int $organizationId, ?int $userId, string $scope): void
    {
        $identity = $userId !== null ? 'user:' . $userId : 'ip:' . hash('sha256', $request->ip() . '|' . (string) env('APP_KEY', ''));
        $limit = match ($scope) {
            'feedback' => 60,
            'report' => 60,
            'intelligence' => 1000,
            default => max(10, (int) env('BUSINESS_CHECKS_PER_HOUR', '120')),
        };
        $window = $scope === 'intelligence' ? 86400 : 3600;
        $key = 'business:' . $scope . ':' . $organizationId . ':' . $identity;
        if (!(new RateLimitService($this->db()))->allow($key, $limit, $window)) {
            header('Retry-After: 3600');
            Response::json(['error' => 'rate_limited'], 429);
        }
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function publicResult(array $result): array
    {
        return [
            'check_id' => $result['check_id'] ?? null,
            'analysis_run_id' => $result['analysis_run_id'] ?? null,
            'status' => $result['status'] ?? null,
            'top_match' => $result['top_match'] ?? null,
            'matches' => $result['matches'] ?? [],
            'ai_analysis' => $result['ai_analysis'] ?? null,
        ];
    }
}
