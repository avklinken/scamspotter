<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ScamRepository;
use App\Services\BusinessAuthService;
use App\Services\BusinessApiKeyService;
use App\Services\BusinessInvitationService;
use App\Services\OrganizationRuleService;
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
            'aiUsage' => $this->aiUsage($organizationId),
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

    public function exportReports(Request $request): never
    {
        require_business_role('analyst');
        $statement = $this->db()->prepare("SELECT r.created_at, r.status, r.description, r.redacted_excerpt,
                oc.input_type, oc.subject, oc.sender_domain, oc.status AS check_status,
                bu.name AS reporter_name
            FROM organization_reports r
            LEFT JOIN organization_checks oc ON oc.id = r.check_id
            LEFT JOIN business_users bu ON bu.id = r.user_id
            WHERE r.organization_id = :organization_id
            ORDER BY r.created_at DESC
            LIMIT 5000");
        $statement->execute(['organization_id' => $this->organizationId()]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="scamspotter-business-reports-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            exit;
        }
        fputcsv($output, ['Datum', 'Status', 'Type', 'Onderwerp', 'Afzenderdomein', 'Checkstatus', 'Melder', 'Redacted excerpt', 'Toelichting']);
        while ($report = $statement->fetch()) {
            fputcsv($output, [
                $this->csvCell((string) ($report['created_at'] ?? '')),
                $this->csvCell((string) ($report['status'] ?? '')),
                $this->csvCell((string) ($report['input_type'] ?? '')),
                $this->csvCell((string) ($report['subject'] ?? '')),
                $this->csvCell((string) ($report['sender_domain'] ?? '')),
                $this->csvCell((string) ($report['check_status'] ?? '')),
                $this->csvCell((string) ($report['reporter_name'] ?? '')),
                $this->csvCell((string) ($report['redacted_excerpt'] ?? '')),
                $this->csvCell((string) ($report['description'] ?? '')),
            ]);
        }
        fclose($output);
        $this->event('reports_exported', 'organization_reports', 0);
        exit;
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
        $plan = (new PlanService($this->db()))->forOrganization($this->organizationId());
        $this->renderBusiness('business/settings', [
            'organization' => $organization,
            'tenant' => $tenant->fetch() ?: null,
            'plan' => $plan,
            'members' => $this->members(),
            'organizationRules' => (new OrganizationRuleService($this->db()))->list($this->organizationId()),
            'apiKeys' => (new BusinessApiKeyService($this->db()))->list($this->organizationId()),
        ]);
    }

    public function updateRetention(Request $request): never
    {
        require_business_role('admin');
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            flash('error', 'Ongeldige sessie. Probeer opnieuw.');
            Response::redirect(url('/business/settings'));
        }
        $days = (int) $request->post('retention_days', 0);
        if ($days < 1 || $days > 365) {
            flash('error', 'Kies een bewaartermijn tussen 1 en 365 dagen.');
            Response::redirect(url('/business/settings'));
        }
        $statement = $this->db()->prepare('UPDATE organizations SET retention_days = :retention_days WHERE id = :id');
        $statement->execute(['retention_days' => $days, 'id' => $this->organizationId()]);
        $this->event('retention_days_updated', 'organization', $this->organizationId());
        flash('success', 'Bewaartermijn bijgewerkt. Dit geldt voor nieuwe zakelijke checks.');
        Response::redirect(url('/business/settings'));
    }

    public function createInvitation(Request $request): never
    {
        require_business_role('admin');
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            flash('error', 'Ongeldige sessie. Probeer opnieuw.');
            Response::redirect(url('/business/settings'));
        }
        $plan = (new PlanService($this->db()))->forOrganization($this->organizationId());
        $countStatement = $this->db()->prepare('SELECT COUNT(*) FROM organization_memberships WHERE organization_id = :organization_id AND status = \'active\'');
        $countStatement->execute(['organization_id' => $this->organizationId()]);
        $memberCount = (int) $countStatement->fetchColumn();
        if ($memberCount >= (int) ($plan['plan']['max_users'] ?? 25)) {
            flash('error', 'Het maximumaantal gebruikers van dit plan is bereikt.');
            Response::redirect(url('/business/settings'));
        }
        try {
            $invite = (new BusinessInvitationService($this->db()))->create(
                $this->organizationId(),
                (string) $request->post('email', ''),
                (string) $request->post('role', 'member'),
            );
            $this->event('member_invitation_created', 'organization_invitation', $invite['id']);
            flash('invite_link', url('/business/invite/' . $invite['token']));
            flash('success', 'Uitnodiging aangemaakt. Deel de link veilig met de medewerker.');
        } catch (\InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        Response::redirect(url('/business/settings'));
    }

    public function invitationForm(Request $request, string $token): void
    {
        $invite = (new BusinessInvitationService($this->db()))->find($token);
        $this->renderBusiness('business/invite', [
            'invite' => $invite,
            'token' => $token,
            'errors' => $invite === null ? ['Deze uitnodiging is verlopen of al gebruikt.'] : [],
        ], $invite === null ? 410 : 200);
    }

    public function acceptInvitation(Request $request, string $token): void
    {
        $invite = (new BusinessInvitationService($this->db()))->find($token);
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            $this->renderBusiness('business/invite', ['invite' => $invite, 'token' => $token, 'errors' => ['Ongeldige sessie. Probeer opnieuw.']], 419);
            return;
        }
        try {
            (new BusinessInvitationService($this->db()))->accept($token, (string) $request->post('name', ''), (string) $request->post('password', ''));
            flash('success', 'Je account is aangemaakt. Je kunt nu inloggen bij ScamSpotter Business.');
            Response::redirect(url('/business/login'));
        } catch (\InvalidArgumentException $exception) {
            $this->renderBusiness('business/invite', ['invite' => $invite, 'token' => $token, 'errors' => [$exception->getMessage()]], 422);
        }
    }

    public function createApiKey(Request $request): never
    {
        require_business_role('admin');
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            flash('error', 'Ongeldige sessie. Probeer opnieuw.');
            Response::redirect(url('/business/settings'));
        }
        $plan = (new PlanService($this->db()))->forOrganization($this->organizationId());
        if (!(bool) ($plan['plan']['api'] ?? false)) {
            flash('error', 'API-toegang is vanaf Business Team beschikbaar.');
            Response::redirect(url('/business/settings'));
        }
        try {
            $created = (new BusinessApiKeyService($this->db()))->create($this->organizationId(), (string) $request->post('name', ''));
            $this->event('api_key_created', 'business_api_key', $created['id']);
            flash('api_key', $created['token']);
            flash('success', 'API-key aangemaakt. Bewaar de key nu; hij wordt niet opnieuw getoond.');
        } catch (\InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        Response::redirect(url('/business/settings'));
    }

    public function createOrganizationRule(Request $request): never
    {
        require_business_role('admin');
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            flash('error', 'Ongeldige sessie. Probeer opnieuw.');
            Response::redirect(url('/business/settings'));
        }
        try {
            $rule = (new OrganizationRuleService($this->db()))->create(
                $this->organizationId(),
                (string) $request->post('rule_type', ''),
                (string) $request->post('value', ''),
                (string) $request->post('label', ''),
                (string) $request->post('explanation', ''),
                $this->userId(),
            );
            $this->event('organization_rule_created', 'organization_rule', $rule['id']);
            flash('success', 'Organisatiecontext opgeslagen.');
        } catch (\InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        Response::redirect(url('/business/settings'));
    }

    public function deleteOrganizationRule(Request $request): never
    {
        require_business_role('admin');
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            flash('error', 'Ongeldige sessie. Probeer opnieuw.');
            Response::redirect(url('/business/settings'));
        }
        $ruleId = (int) $request->post('id', 0);
        if ((new OrganizationRuleService($this->db()))->delete($this->organizationId(), $ruleId)) {
            $this->event('organization_rule_deleted', 'organization_rule', $ruleId);
            flash('success', 'Organisatiecontext verwijderd.');
        } else {
            flash('error', 'Organisatiecontext niet gevonden.');
        }
        Response::redirect(url('/business/settings'));
    }

    public function revokeApiKey(Request $request): never
    {
        require_business_role('admin');
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            flash('error', 'Ongeldige sessie. Probeer opnieuw.');
            Response::redirect(url('/business/settings'));
        }
        $keyId = (int) $request->post('id', 0);
        if ((new BusinessApiKeyService($this->db()))->revoke($this->organizationId(), $keyId)) {
            $this->event('api_key_revoked', 'business_api_key', $keyId);
            flash('success', 'API-key ingetrokken.');
        } else {
            flash('error', 'API-key niet gevonden of al ingetrokken.');
        }
        Response::redirect(url('/business/settings'));
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
            'feedback_useful' => "SELECT COUNT(*) FROM organization_check_feedback WHERE organization_id = :id AND feedback = 'useful' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'feedback_not_useful' => "SELECT COUNT(*) FROM organization_check_feedback WHERE organization_id = :id AND feedback = 'not_useful' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
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

    /** @return array{runs: int, input_tokens: int, output_tokens: int} */
    private function aiUsage(int $organizationId): array
    {
        $statement = $this->db()->prepare('SELECT COUNT(*) AS runs,
                COALESCE(SUM(input_tokens), 0) AS input_tokens,
                COALESCE(SUM(output_tokens), 0) AS output_tokens
            FROM usage_events WHERE organization_id = :organization_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
        $statement->execute(['organization_id' => $organizationId]);
        $usage = $statement->fetch() ?: [];
        return [
            'runs' => (int) ($usage['runs'] ?? 0),
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
        ];
    }

    private function organizationId(): int
    {
        return (int) (business_user()['organization_id'] ?? 0);
    }

    private function userId(): int
    {
        return (int) (business_user()['id'] ?? 0);
    }

    private function csvCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'" . $value : $value;
    }

    /** @return list<array<string, mixed>> */
    private function members(): array
    {
        $statement = $this->db()->prepare('SELECT bu.name, bu.email, bu.last_login_at, om.role, om.status
            FROM organization_memberships om INNER JOIN business_users bu ON bu.id = om.user_id
            WHERE om.organization_id = :organization_id ORDER BY FIELD(om.role, \'owner\', \'admin\', \'analyst\', \'member\'), bu.name');
        $statement->execute(['organization_id' => $this->organizationId()]);
        return $statement->fetchAll();
    }

    private function event(string $type, string $entityType, int $entityId): void
    {
        $statement = $this->db()->prepare('INSERT INTO organization_events (organization_id, user_id, event_type, entity_type, entity_id)
            VALUES (:organization_id, :user_id, :event_type, :entity_type, :entity_id)');
        $statement->execute([
            'organization_id' => $this->organizationId(),
            'user_id' => $this->userId() ?: null,
            'event_type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function renderBusiness(string $view, array $data = [], int $status = 200): void
    {
        $data['seo'] ??= (new SeoService())->metadata(['title' => 'ScamSpotter Business', 'robots' => 'noindex,nofollow']);
        $this->render($view, $data, $status);
    }
}
