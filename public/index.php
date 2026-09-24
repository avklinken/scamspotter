<?php
declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CheckerController;
use App\Controllers\PublicController;
use App\Controllers\ReportController;
use App\Http\Request;
use App\Router;

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$GLOBALS['app'] = $app;
start_session();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$router = new Router();
$request = new Request();
$redirect = $app['db']->prepare('SELECT new_path, status_code FROM redirects WHERE old_path = :old_path AND active = 1 LIMIT 1');
$redirect->execute(['old_path' => $request->path()]);
if ($target = $redirect->fetch()) {
    header('Location: ' . url((string) $target['new_path']), true, (int) $target['status_code']);
    exit;
}
$public = [PublicController::class, 'home'];
$router->get('/', $public);
$router->get('/oplichting', [PublicController::class, 'encyclopedia']);
$router->get('/oplichting/{slug}', [PublicController::class, 'scam']);
$router->get('/waarschuwingen', [PublicController::class, 'alerts']);
$router->get('/waarschuwingen/{slug}', [PublicController::class, 'alert']);
$router->get('/zoeken', [PublicController::class, 'search']);
$router->get('/bronnen', [PublicController::class, 'sources']);
$router->get('/over-scamspotter', static fn (Request $request): mixed => (new PublicController($app))->staticPage($request, 'over-scamspotter'));
$router->get('/over-scamspotter/werkwijze', [PublicController::class, 'methodology']);
$router->get('/privacy', static fn (Request $request): mixed => (new PublicController($app))->staticPage($request, 'privacy'));
$router->get('/cookies', static fn (Request $request): mixed => (new PublicController($app))->staticPage($request, 'cookies'));
$router->get('/contact', static fn (Request $request): mixed => (new PublicController($app))->staticPage($request, 'contact'));
$router->get('/sitemap.xml', [PublicController::class, 'sitemap']);
$router->get('/robots.txt', [PublicController::class, 'robots']);

$router->get('/check', [CheckerController::class, 'show']);
$router->post('/check', [CheckerController::class, 'run']);
$router->get('/check/{landing}', [CheckerController::class, 'landing']);

$router->get('/melden', [ReportController::class, 'form']);
$router->post('/melden', [ReportController::class, 'submit']);

$router->get('/admin/login', [AuthController::class, 'loginForm']);
$router->post('/admin/login', [AuthController::class, 'login']);
$router->post('/admin/logout', [AuthController::class, 'logout']);
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/scams', [AdminController::class, 'scams']);
$router->get('/admin/alerts', [AdminController::class, 'alerts']);
$router->get('/admin/sources', [AdminController::class, 'sources']);
$router->post('/admin/sources/save', [AdminController::class, 'saveSource']);
$router->get('/admin/review', [AdminController::class, 'review']);
$router->post('/admin/review/action', [AdminController::class, 'reviewAction']);
$router->get('/admin/reports', [AdminController::class, 'reports']);
$router->post('/admin/reports/action', [AdminController::class, 'reportAction']);
$router->get('/admin/cron', [AdminController::class, 'cron']);
$router->get('/admin/seo', [AdminController::class, 'seo']);
$router->get('/admin/settings', [AdminController::class, 'settings']);

$router->dispatch($request);
