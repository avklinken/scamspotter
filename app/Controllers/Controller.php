<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;

abstract class Controller
{
    public function __construct(protected array $app)
    {
    }

    protected function db(): PDO
    {
        return $this->app['db'];
    }

    /** @param array<string, mixed> $data */
    protected function render(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        extract($data, EXTR_SKIP);
        $viewFile = BASE_PATH . '/resources/views/' . $view . '.php';
        if (!is_readable($viewFile)) {
            throw new \RuntimeException('View not found: ' . $view);
        }
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();
        $layout = str_starts_with($view, 'admin/') ? 'admin' : (str_starts_with($view, 'auth/') ? 'auth' : 'app');
        require BASE_PATH . '/resources/views/layouts/' . $layout . '.php';
    }

    protected function redirect(string $path): never
    {
        \App\Http\Response::redirect(url($path));
    }
}
