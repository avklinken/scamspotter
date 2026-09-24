<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\RateLimitService;
use App\Services\SeoService;

final class AuthController extends Controller
{
    public function loginForm(Request $request): void
    {
        if (auth_user() !== null) {
            Response::redirect(url('/admin'));
        }
        $this->render('auth/login', [
            'seo' => (new SeoService())->metadata([
                'title' => 'Beheerlogin — ScamSpotter.nl',
                'robots' => 'noindex,nofollow',
            ]),
            'error' => null,
        ]);
    }

    public function login(Request $request): void
    {
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            http_response_code(419);
            echo 'Ongeldige sessie. Probeer opnieuw.';
            return;
        }
        $email = mb_strtolower(trim((string) $request->post('email', '')));
        $secret = (string) env('APP_KEY', '');
        $limiter = new RateLimitService($this->db());
        $ipKey = 'admin-login:ip:' . hash('sha256', $request->ip() . '|' . $secret);
        $emailKey = 'admin-login:email:' . hash('sha256', $email . '|' . $secret);
        if (!$limiter->allow($ipKey, 10, 600) || !$limiter->allow($emailKey, 10, 600)) {
            $this->render('auth/login', [
                'seo' => (new SeoService())->metadata(['title' => 'Beheerlogin — ScamSpotter.nl', 'robots' => 'noindex,nofollow']),
                'error' => 'Te veel inlogpogingen. Probeer het over enkele minuten opnieuw.',
            ], 429);
            return;
        }
        $password = (string) $request->post('password', '');
        $statement = $this->db()->prepare('SELECT * FROM admin_users WHERE email = :email AND is_active = 1 LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
            $this->render('auth/login', [
                'seo' => (new SeoService())->metadata(['title' => 'Beheerlogin — ScamSpotter.nl', 'robots' => 'noindex,nofollow']),
                'error' => 'De combinatie van e-mailadres en wachtwoord klopt niet.',
            ], 422);
            return;
        }
        session_regenerate_id(true);
        $_SESSION['admin_user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];
        $update = $this->db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => $user['id']]);
        $this->audit('admin_login', 'admin_user', (int) $user['id'], []);
        Response::redirect(url('/admin'));
    }

    public function logout(Request $request): void
    {
        if ($request->isPost() && verify_csrf($request->post('_csrf'))) {
            $user = auth_user();
            if ($user !== null) {
                $this->audit('admin_logout', 'admin_user', (int) $user['id'], []);
            }
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
            }
            session_destroy();
        }
        Response::redirect(url('/admin/login'));
    }

    /** @param array<string, mixed> $context */
    private function audit(string $action, string $entityType, int $entityId, array $context): void
    {
        $statement = $this->db()->prepare("INSERT INTO audit_log
            (admin_user_id, action, entity_type, entity_id, context_json, ip_hash, created_at)
            VALUES (:user_id, :action, :entity_type, :entity_id, :context_json, :ip_hash, NOW())");
        $user = auth_user();
        $statement->execute([
            'user_id' => $user['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'context_json' => json_text($context),
            'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) env('APP_KEY', '')),
        ]);
    }
}
