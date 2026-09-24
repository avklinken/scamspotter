<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ScamRepository;
use App\Services\BusinessAuthService;
use App\Services\OpenAIService;
use App\Services\OrganizationCheckService;
use App\Services\PlanService;
use App\Services\PlanLimitException;
use App\Services\ScamAnalysisOrchestrator;
use App\Services\ScamAnalysisService;
use App\Services\SeoService;

final class BusinessController extends Controller
{
    public function dashboard(Request $request): void
    {
        require_business_user();
        $organizationId = $this->organizationId();
        $this->renderBusiness('business/dashboard', [
            'metrics' => $this->metrics($organizationId),
            'recentReports' => $this->recentReports($organizationId, 6),
            'topTypes' => $this->topTypes($organizationId),
            'plan' => (new PlanService($this->db()))->forOrganization($organizationId),
            'planUsage' => (new PlanService($this->db()))->usage($organizationId),
        ]);
    }

    public function checkForm(Request $request): void
    {
        require_business_user();
        $this->renderBusiness('business/check', ['result' => null, 'errors' => []]);
    }

    public function runCheck(Request $request): void
    {
        require_business_user();
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            http_response_code(419);
            echo 'Ongeldige sessie. Probeer opnieuw.';
            return;
        }
        try {
            $result = $this->checkService()->run($this->organizationId(), $this->userId(), [
                'input_type' => $request->post('input_type', 'email'),
                'channel' => 'business_web',
                'subject' => $request->post('subject', ''),
                'sender_email' => $request->post('sender_email', ''),
                'body' => $request->post('body', ''),
            ]);
            $this->renderBusiness('business/check-result', ['result' => $result, 'checkId' => (int) $result['check_id']]);
        } catch (PlanLimitException $exception) {
            $this->renderBusiness('business/check', ['result' => null, 'errors' => [$exception->getMessage()]], 429);
        } catch (\InvalidArgumentException $exception) {
            $this->renderBusiness('business/check', ['result' => null, 'errors' => [$exception->getMessage()]], 422);
        }
    }

    public function reports(Request $request): void
    {
        require_business_role('analyst');
        $this->renderBusiness('business/reports', ['reports' => $this->recentReports($this->organizationId(), 100)]);
    }

    public function reportAction(Request $request): never
    {
        require_business_role('analyst');
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            Response::redirect(url('/business/reports'));
        }
        $id = (int) $request->post('id', 0);
        $status = (string) $request->post('status', '');
        if ($id > 0 && in_array($status, ['new', 'review', 'resolved', 'dismissed'], true)) {
            $statement = $this->db()->prepare("UPDATE organization_reports SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :id AND organization_id = :organization_id");
            $statement->execute(['status' => $status, 'reviewed_by' => $this->userId(), 'id' => $id, 'organization_id' => $this->organizationId()]);
            flash('success', 'Organisatiemelding bijgewerkt.');
        }
        Response::redirect(url('/business/reports'));
    }

    public function settings(Request $request): void
    {
        require_business_role('admin');
        $statement = $this->db()->prepare('SELECT * FROM organizations WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $this->organizationId()]);
        $organization = $statement->fetch();
        $tenant = $this->db()->prepare('SELECT * FROM microsoft_tenants WHERE organization_id = :organization_id ORDER BY id DESC LIMIT 1');
        $tenant->execute(['organization_id' => $this->organizationId()]);
        $this->renderBusiness('business/settings', ['organization' => $organization, 'tenant' => $tenant->fetch() ?: null]);
    }

    private function checkService(): OrganizationCheckService
    {
        $local = new ScamAnalysisService(new ScamRepository($this->db()));
        return new OrganizationCheckService($this->db(), new ScamAnalysisOrchestrator($local, new OpenAIService()));
    }

    /** @return array<string, int> */
    private function metrics(int $organizationId): array
    {
        $metrics = [];
        foreach ([
            'checks' => "SELECT COUNT(*) FROM organization_checks WHERE organization_id = :id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'reports' => "SELECT COUNT(*) FROM organization_reports WHERE organization_id = :id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'suspicious' => "SELECT COUNT(*) FROM organization_checks WHERE organization_id = :id AND status IN ('strong_match', 'suspicious_signals') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'open_reports' => "SELECT COUNT(*) FROM organization_reports WHERE organization_id = :id AND status IN ('new', 'review')",
            'members' => "SELECT COUNT(*) FROM organization_memberships WHERE organization_id = :id AND status = 'active'",
        ] as $key => $sql) {
            $statement = $this->db()->prepare($sql);
            $statement->execute(['id' => $organizationId]);
            $metrics[$key] = (int) $statement->fetchColumn();
        }
        return $metrics;
    }

    /** @return list<array<string, mixed>> */
    private function recentReports(int $organizationId, int $limit): array
    {
        $statement = $this->db()->prepare("SELECT r.*, bu.name AS reporter_name, oc.subject, oc.sender_domain, oc.status AS check_status
            FROM organization_reports r
            LEFT JOIN business_users bu ON bu.id = r.user_id
            LEFT JOIN organization_checks oc ON oc.id = r.check_id
            WHERE r.organization_id = :organization_id ORDER BY r.created_at DESC LIMIT " . max(1, min($limit, 100)));
        $statement->execute(['organization_id' => $organizationId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    private function topTypes(int $organizationId): array
    {
        $statement = $this->db()->prepare("SELECT JSON_UNQUOTE(JSON_EXTRACT(r.result_json, '$.top_match.variant.type_name')) AS type_name, COUNT(*) AS total
            FROM organization_analysis_runs r WHERE r.organization_id = :organization_id AND r.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY type_name HAVING type_name IS NOT NULL ORDER BY total DESC LIMIT 5");
        $statement->execute(['organization_id' => $organizationId]);
        return $statement->fetchAll();
    }

    private function organizationId(): int
    {
        return (int) (business_user()['organization_id'] ?? 0);
    }

    private function userId(): int
    {
        return (int) (business_user()['id'] ?? 0);
    }

    /** @param array<string, mixed> $data */
    private function renderBusiness(string $view, array $data = [], int $status = 200): void
    {
        $data['seo'] ??= (new SeoService())->metadata(['title' => 'ScamSpotter Business', 'robots' => 'noindex,nofollow']);
        $this->render($view, $data, $status);
    }
}
