<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\AlertRepository;
use App\Repositories\ScamRepository;
use App\Repositories\SourceRepository;
use App\Repositories\SubmissionRepository;
use App\Services\SeoService;
use App\Services\BusinessProvisioningService;

final class AdminController extends Controller
{
    public function dashboard(Request $request): void
    {
        require_admin();
        $this->renderAdmin('admin/dashboard', [
            'metrics' => [
                'types' => $this->count("SELECT COUNT(*) FROM scam_types WHERE status = 'published'"),
                'alerts' => $this->count("SELECT COUNT(*) FROM scam_alerts WHERE status = 'published'"),
                'imports' => $this->count("SELECT COUNT(*) FROM source_items WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
                'reviews' => $this->count("SELECT COUNT(*) FROM review_queue WHERE status = 'pending'"),
                'reports' => $this->count("SELECT COUNT(*) FROM submissions WHERE status IN ('new', 'review')"),
                'sources' => $this->count("SELECT COUNT(*) FROM sources WHERE active = 1 AND last_success_at >= CURDATE()"),
                'failures' => $this->count("SELECT COUNT(*) FROM sources WHERE last_error_at >= CURDATE()"),
            ],
            'lastCron' => $this->first('SELECT * FROM cron_runs ORDER BY started_at DESC LIMIT 1'),
            'reviewItems' => $this->reviewItems(5),
        ]);
    }

    public function business(Request $request): void
    {
        require_admin();
        $this->renderAdmin('admin/business', ['organizations' => $this->organizations()]);
    }

    public function createBusiness(Request $request): never
    {
        require_admin();
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            http_response_code(419);
            echo 'Ongeldige sessie.';
            exit;
        }
        try {
            $result = (new BusinessProvisioningService($this->db()))->provision(
                (string) $request->post('email', ''),
                (string) $request->post('name', ''),
                (string) $request->post('organization_name', ''),
                (string) $request->post('password', ''),
                (string) $request->post('slug', ''),
            );
            $this->audit('business_organization_created', 'organization', $result['organization_id'], ['account_id' => $result['account_id']]);
            flash('success', 'Business-pilot aangemaakt. De owner kan nu inloggen.');
        } catch (\InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        Response::redirect(url('/admin/business'));
    }

    public function updateBusinessStatus(Request $request): never
    {
        require_admin();
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            flash('error', 'Ongeldige sessie.');
            Response::redirect(url('/admin/business'));
        }
        $organizationId = (int) $request->post('id', 0);
        $status = (string) $request->post('status', '');
        if ($organizationId < 1 || !in_array($status, ['trial', 'active', 'suspended', 'closed'], true)) {
            flash('error', 'Ongeldige Business-status.');
            Response::redirect(url('/admin/business'));
        }
        $statement = $this->db()->prepare('UPDATE organizations SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => $organizationId]);
        if ($statement->rowCount() > 0) {
            $this->audit('business_organization_status_changed', 'organization', $organizationId, ['status' => $status]);
            flash('success', 'Business-status bijgewerkt.');
        } else {
            flash('error', 'Organisatie niet gevonden of status was al gelijk.');
        }
        Response::redirect(url('/admin/business'));
    }

    /** @return list<array<string, mixed>> */
    private function organizations(): array
    {
        $organizations = $this->db()->query("SELECT o.id, o.name, o.slug, o.status, o.retention_days,
                ca.billing_email,
                COALESCE(sub.plan_code, 'business_trial') AS plan_code,
                COALESCE(sub.status, 'trialing') AS subscription_status,
                COUNT(DISTINCT om.user_id) AS members,
                COUNT(DISTINCT CASE WHEN oc.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN oc.id END) AS checks_30d,
                COUNT(DISTINCT CASE WHEN r.status IN ('new', 'review') THEN r.id END) AS open_reports
            FROM organizations o
            INNER JOIN commercial_accounts ca ON ca.id = o.account_id
            LEFT JOIN account_subscriptions sub ON sub.account_id = ca.id AND sub.status IN ('trialing', 'active')
            LEFT JOIN organization_memberships om ON om.organization_id = o.id AND om.status = 'active'
            LEFT JOIN organization_checks oc ON oc.organization_id = o.id
            LEFT JOIN organization_reports r ON r.organization_id = o.id
            GROUP BY o.id, o.name, o.slug, o.status, o.retention_days, ca.billing_email, sub.plan_code, sub.status
            ORDER BY o.created_at DESC")->fetchAll();
        return $organizations;
    }

    public function scams(Request $request): void
    {
        require_admin();
        $this->renderAdmin('admin/scams', [
            'families' => (new ScamRepository($this->db()))->families(),
            'types' => (new ScamRepository($this->db()))->types(),
            'variants' => (new ScamRepository($this->db()))->variants(),
        ]);
    }

    public function alerts(Request $request): void
    {
        require_admin();
        $statement = $this->db()->query("SELECT a.*, v.name AS variant_name, s.organization AS source_organization
            FROM scam_alerts a LEFT JOIN scam_variants v ON v.id = a.variant_id LEFT JOIN sources s ON s.id = a.source_id
            ORDER BY a.created_at DESC LIMIT 100");
        $this->renderAdmin('admin/alerts', ['alerts' => $statement->fetchAll()]);
    }

    public function sources(Request $request): void
    {
        require_admin();
        $this->renderAdmin('admin/sources', ['sources' => (new SourceRepository($this->db()))->all()]);
    }

    public function saveSource(Request $request): void
    {
        require_admin();
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            http_response_code(419);
            echo 'Ongeldige sessie.';
            return;
        }
        $organization = trim((string) $request->post('organization', ''));
        $homepage = trim((string) $request->post('homepage', ''));
        $feedUrl = trim((string) $request->post('feed_url', ''));
        $notes = trim((string) $request->post('notes', ''));
        if ($organization === '' || ($homepage !== '' && !preg_match('#^https://#i', $homepage)) || ($feedUrl !== '' && !preg_match('#^https://#i', $feedUrl))) {
            flash('success', 'Bron niet opgeslagen: organisatie en HTTPS-URL’s zijn verplicht als ze worden ingevuld.');
            Response::redirect(url('/admin/sources'));
        }
        $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($organization)), '-');
        $statement = $this->db()->prepare("INSERT INTO sources (organization, title, slug, homepage, feed_url, source_type, trust_status, active, crawl_method, notes)
            VALUES (:organization, :title, :slug, :homepage, :feed_url, :source_type, 'pending', 1, :crawl_method, :notes)
            ON DUPLICATE KEY UPDATE organization = VALUES(organization), homepage = VALUES(homepage), feed_url = VALUES(feed_url), notes = VALUES(notes), active = 1");
        $statement->execute([
            'organization' => $organization,
            'title' => $organization,
            'slug' => $slug,
            'homepage' => $homepage !== '' ? $homepage : null,
            'feed_url' => $feedUrl !== '' ? $feedUrl : null,
            'source_type' => (string) $request->post('source_type', 'website'),
            'crawl_method' => (string) $request->post('crawl_method', 'manual'),
            'notes' => $notes !== '' ? $notes : null,
        ]);
        $this->audit('source_saved', 'source', 0, ['slug' => $slug]);
        flash('success', 'Bron opgeslagen. Controleer eerst de trust-status voordat je live import activeert.');
        Response::redirect(url('/admin/sources'));
    }

    public function review(Request $request): void
    {
        require_admin();
        $this->renderAdmin('admin/review', ['items' => $this->reviewItems(100)]);
    }

    public function reports(Request $request): void
    {
        require_admin();
        $this->renderAdmin('admin/reports', ['reports' => (new SubmissionRepository($this->db()))->recent(100)]);
    }

    public function cron(Request $request): void
    {
        require_admin();
        $runs = $this->db()->query('SELECT * FROM cron_runs ORDER BY started_at DESC LIMIT 50')->fetchAll();
        $this->renderAdmin('admin/cron', ['runs' => $runs]);
    }

    public function seo(Request $request): void
    {
        require_admin();
        $checks = [
            ['label' => 'Scamtypes zonder samenvatting', 'value' => $this->count("SELECT COUNT(*) FROM scam_types WHERE status = 'published' AND (summary IS NULL OR summary = '')")],
            ['label' => 'Varianten zonder familie/type', 'value' => $this->count("SELECT COUNT(*) FROM scam_variants v LEFT JOIN scam_types t ON t.id = v.type_id WHERE v.status = 'published' AND t.id IS NULL")],
            ['label' => 'Alerts zonder bron', 'value' => $this->count("SELECT COUNT(*) FROM scam_alerts WHERE status = 'published' AND source_id IS NULL")],
            ['label' => 'Alerts zonder evergreen-link', 'value' => $this->count("SELECT COUNT(*) FROM scam_alerts WHERE status = 'published' AND variant_id IS NULL")],
            ['label' => 'Content ouder dan 180 dagen zonder review', 'value' => $this->count("SELECT COUNT(*) FROM scam_variants WHERE status = 'published' AND COALESCE(reviewed_at, updated_at) < DATE_SUB(NOW(), INTERVAL 180 DAY)")],
        ];
        $this->renderAdmin('admin/seo', ['checks' => $checks]);
    }

    public function settings(Request $request): void
    {
        require_admin();
        $this->renderAdmin('admin/settings', ['settings' => [
            ['label' => 'Omgeving', 'value' => env('APP_ENV', 'local')],
            ['label' => 'Debug', 'value' => env('APP_DEBUG', 'false')],
            ['label' => 'OpenAI', 'value' => env('OPENAI_API_KEY', '') !== '' ? 'Geconfigureerd' : 'Niet geconfigureerd'],
            ['label' => 'Analytics', 'value' => env('ANALYTICS_ID', '') !== '' || env('GTM_ID', '') !== '' ? 'Consent-gebaseerd geconfigureerd' : 'Niet geconfigureerd'],
            ['label' => 'Checker-retentie', 'value' => env('CHECK_RETENTION_DAYS', '7') . ' dagen'],
        ]]);
    }

    public function reviewAction(Request $request): void
    {
        require_admin();
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            http_response_code(419);
            echo 'Ongeldige sessie.';
            return;
        }
        $id = (int) $request->post('id', 0);
        $action = (string) $request->post('action', '');
        $status = match ($action) {
            'approve' => 'approved',
            'ignore' => 'ignored',
            'duplicate' => 'duplicate',
            default => null,
        };
        if ($id > 0 && $status !== null) {
            $statement = $this->db()->prepare("UPDATE review_queue SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW(), notes = :notes WHERE id = :id");
            $statement->execute([
                'status' => $status,
                'reviewed_by' => auth_user()['id'] ?? null,
                'notes' => trim((string) $request->post('notes', '')),
                'id' => $id,
            ]);
            $this->audit('review_' . $status, 'review_queue', $id, []);
            flash('success', 'Review-item bijgewerkt.');
        }
        Response::redirect(url('/admin/review'));
    }

    public function reportAction(Request $request): void
    {
        require_admin();
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            http_response_code(419);
            echo 'Ongeldige sessie.';
            return;
        }
        $id = (int) $request->post('id', 0);
        $status = (string) $request->post('status', '');
        if ($id > 0 && in_array($status, ['new', 'review', 'accepted', 'rejected', 'closed'], true)) {
            (new SubmissionRepository($this->db()))->updateStatus($id, $status);
            $this->audit('report_' . $status, 'submission', $id, []);
            flash('success', 'Melding bijgewerkt.');
        }
        Response::redirect(url('/admin/reports'));
    }

    /** @return list<array<string, mixed>> */
    private function reviewItems(int $limit): array
    {
        $statement = $this->db()->query("SELECT rq.*, si.title AS source_title, si.url AS source_url,
            ai.classification, ai.output_json, s.organization AS source_organization
            FROM review_queue rq
            LEFT JOIN source_items si ON si.id = rq.source_item_id
            LEFT JOIN ai_analyses ai ON ai.id = rq.ai_analysis_id
            LEFT JOIN sources s ON s.id = si.source_id
            WHERE rq.status = 'pending' ORDER BY rq.priority DESC, rq.created_at DESC LIMIT " . max(1, min($limit, 100)));
        return $statement->fetchAll();
    }

    private function count(string $sql): int
    {
        return (int) $this->db()->query($sql)->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    private function first(string $sql): ?array
    {
        $row = $this->db()->query($sql)->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    private function renderAdmin(string $view, array $data = []): void
    {
        $data['seo'] = (new SeoService())->metadata(['title' => 'Beheer — ScamSpotter.nl', 'robots' => 'noindex,nofollow']);
        $this->render($view, $data);
    }

    /** @param array<string, mixed> $context */
    private function audit(string $action, string $entityType, int $entityId, array $context): void
    {
        $statement = $this->db()->prepare("INSERT INTO audit_log
            (admin_user_id, action, entity_type, entity_id, context_json, ip_hash, created_at)
            VALUES (:user_id, :action, :entity_type, :entity_id, :context_json, :ip_hash, NOW())");
        $statement->execute([
            'user_id' => auth_user()['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'context_json' => json_text($context),
            'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) env('APP_KEY', '')),
        ]);
    }
}
