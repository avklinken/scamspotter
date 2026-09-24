<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\BusinessAuthService;
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
        $user = (new BusinessAuthService($this->db()))->login(
            (string) $request->post('email', ''),
            (string) $request->post('password', ''),
        );
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
        $user = (new BusinessAuthService($this->db()))->login((string) ($data['email'] ?? ''), (string) ($data['password'] ?? ''));
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
}
