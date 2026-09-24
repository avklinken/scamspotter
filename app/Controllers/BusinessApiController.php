<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ScamRepository;
use App\Services\BusinessAuthService;
use App\Services\OpenAIService;
use App\Services\OrganizationCheckService;
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

    public function report(Request $request): never
    {
        [$organizationId, $userId, $sessionAuth] = $this->authenticate($request);
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
