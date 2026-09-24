<?php
declare(strict_types=1);

namespace App;

use App\Http\Request;
use App\Http\Response;
use Closure;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: Closure|array}> */
    private array $routes = [];

    public function get(string $pattern, Closure|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, Closure|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, Closure|array $handler): void
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $this->routes[] = compact('method', 'pattern', 'handler');
    }

    public function dispatch(Request $request): mixed
    {
        $path = rtrim($request->path(), '/') ?: '/';
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            $keys = [];
            $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $match) use (&$keys): string {
                $keys[] = $match[1];
                return '([^/]+)';
            }, $route['pattern']);
            if (!is_string($regex) || preg_match('#^' . $regex . '/?$#', $path, $matches) !== 1) {
                continue;
            }

            array_shift($matches);
            $params = [];
            foreach ($keys as $index => $key) {
                $params[$key] = urldecode($matches[$index] ?? '');
            }
            return is_array($route['handler'])
                ? (new $route['handler'][0]($this->dependencies()))->{$route['handler'][1]}($request, ...array_values($params))
                : ($route['handler'])($request, ...array_values($params));
        }

        Response::notFound('Pagina niet gevonden');
    }

    /** @return array<string, mixed> */
    private function dependencies(): array
    {
        return $GLOBALS['app'] ?? [];
    }
}
