<?php
declare(strict_types=1);

use App\Support\Env;

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) env('APP_URL', ''), '/');
        if ($path === '') {
            return $base !== '' ? $base : '/';
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }
        return ($base !== '' ? $base : '') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('safe_external_url')) {
    function safe_external_url(mixed $value): string
    {
        $value = trim((string) $value);
        return preg_match('#^https?://#i', $value) === 1 && filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : '#';
    }
}

if (!function_exists('is_https')) {
    function is_https(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }
}

if (!function_exists('start_session')) {
    function start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name((string) env('SESSION_NAME', 'scamspotter_session'));
        session_set_cookie_params([
            'httponly' => true,
            'secure' => is_https(),
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        start_session();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool
    {
        start_session();
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals((string) $_SESSION['_csrf'], $token);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $value = null): ?string
    {
        start_session();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return is_string($message) ? $message : null;
    }
}

if (!function_exists('auth_user')) {
    /** @return array<string, mixed>|null */
    function auth_user(): ?array
    {
        start_session();
        return isset($_SESSION['admin_user']) && is_array($_SESSION['admin_user']) ? $_SESSION['admin_user'] : null;
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        if (auth_user() === null) {
            header('Location: ' . url('/admin/login'));
            exit;
        }
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        start_session();
        return e((string) ($_SESSION['_old'][$key] ?? $default));
    }
}

if (!function_exists('remember_old')) {
    /** @param array<string, mixed> $values */
    function remember_old(array $values): void
    {
        start_session();
        $_SESSION['_old'] = $values;
    }
}

if (!function_exists('forget_old')) {
    function forget_old(): void
    {
        start_session();
        unset($_SESSION['_old']);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'j F Y'): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        try {
            $dateTime = new DateTimeImmutable($date);
            $months = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
            return str_replace(
                ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                $months,
                $dateTime->format($format),
            );
        } catch (Throwable) {
            return '';
        }
    }
}

if (!function_exists('status_label')) {
    function status_label(string $status): string
    {
        return match ($status) {
            'published' => 'Gepubliceerd',
            'active' => 'Actief',
            'review' => 'In beoordeling',
            'draft' => 'Concept',
            'archived' => 'Gearchiveerd',
            'closed' => 'Afgerond',
            'new' => 'Nieuw',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}

if (!function_exists('json_text')) {
    function json_text(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
    }
}
