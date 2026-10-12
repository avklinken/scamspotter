<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\BusinessAuthService;
use App\Services\MicrosoftIdentityService;
use App\Services\RateLimitService;
use App\Services\SeoService;

final class BusinessAuthController extends Controller
{
    public function loginForm(Request $request): void
    {
        if (business_user() !== null) {
            Response::redirect(url('/business'));
        }
        $this->render('business/login', [
            'seo' => (new SeoService())->metadata(['title' => 'ScamSpotter Business login', 'robots' => 'noindex,nofollow']),
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
        $email = (string) $request->post('email', '');
        if (!$this->loginAllowed($request, $email)) {
            $this->render('business/login', [
                'seo' => (new SeoService())->metadata(['title' => 'ScamSpotter Business login', 'robots' => 'noindex,nofollow']),
                'error' => 'Te veel inlogpogingen. Probeer het over enkele minuten opnieuw.',
            ], 429);
            return;
        }
        $user = (new BusinessAuthService($this->db()))->login($email, (string) $request->post('password', ''));
        if ($user === null) {
            $this->render('business/login', [
                'seo' => (new SeoService())->metadata(['title' => 'ScamSpotter Business login', 'robots' => 'noindex,nofollow']),
                'error' => 'De combinatie van e-mailadres en wachtwoord klopt niet.',
            ], 422);
            return;
        }
        session_regenerate_id(true);
        $_SESSION['business_user'] = [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'name' => (string) $user['name'],
            'organization_id' => (int) $user['organization_id'],
            'organization_name' => (string) $user['organization_name'],
            'role' => (string) $user['role'],
        ];
        Response::redirect(url('/business'));
    }

    public function apiLogin(Request $request): never
    {
        $data = $request->json();
        $email = (string) ($data['email'] ?? '');
        if (!$this->loginAllowed($request, $email)) {
            Response::json(['error' => 'rate_limited'], 429);
        }
        $user = (new BusinessAuthService($this->db()))->login($email, (string) ($data['password'] ?? ''));
        if ($user === null) {
            Response::json(['error' => 'invalid_credentials'], 422);
        }
        session_regenerate_id(true);
        $_SESSION['business_user'] = [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'name' => (string) $user['name'],
            'organization_id' => (int) $user['organization_id'],
            'organization_name' => (string) $user['organization_name'],
            'role' => (string) $user['role'],
        ];
        Response::json(['authenticated' => true, 'user' => business_user(), 'csrf' => csrf_token()]);
    }

    public function apiMicrosoftSso(Request $request): never
    {
        if (!MicrosoftIdentityService::enabled()) {
            Response::json(['error' => 'sso_not_configured', 'message' => 'Microsoft 365-aanmelding is nog niet geconfigureerd.'], 503);
        }
        $token = $request->bearerToken();
        if ($token === null) {
            Response::json(['error' => 'sso_token_required', 'message' => 'Microsoft 365-aanmelding ontbreekt.'], 401);
        }
        try {
            $user = (new BusinessAuthService($this->db()))->loginWithMicrosoftToken($token);
        } catch (\Throwable $exception) {
            file_put_contents(BASE_PATH . '/storage/logs/app.log', '[' . date('c') . '] business_sso ' . $exception->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            Response::json(['error' => 'sso_unavailable', 'message' => 'Microsoft 365-aanmelding kan nu niet worden gecontroleerd.'], 503);
        }
        if ($user === null) {
            Response::json(['error' => 'sso_identity_not_allowed', 'message' => 'Je Microsoft 365-account is nog niet als Business-lid toegevoegd.'], 403);
        }
        $this->storeSession($user);
        Response::json(['authenticated' => true, 'user' => business_user(), 'csrf' => csrf_token()]);
    }

    public function logout(Request $request): void
    {
        if ($request->isPost() && verify_csrf($request->post('_csrf'))) {
            unset($_SESSION['business_user']);
            session_regenerate_id(true);
        }
        Response::redirect(url('/business/login'));
    }

    public function apiLogout(Request $request): never
    {
        unset($_SESSION['business_user']);
        Response::json(['authenticated' => false]);
    }

    private function loginAllowed(Request $request, string $email): bool
    {
        $limiter = new RateLimitService($this->db());
        $secret = (string) env('APP_KEY', '');
        $ipKey = 'business-login:ip:' . hash('sha256', $request->ip() . '|' . $secret);
        $emailKey = 'business-login:email:' . hash('sha256', mb_strtolower(trim($email)) . '|' . $secret);
        return $limiter->allow($ipKey, 10, 600) && $limiter->allow($emailKey, 10, 600);
    }

    /** @param array<string, mixed> $user */
    private function storeSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['business_user'] = [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'name' => (string) $user['name'],
            'organization_id' => (int) $user['organization_id'],
            'organization_name' => (string) $user['organization_name'],
            'role' => (string) $user['role'],
        ];
    }
}
