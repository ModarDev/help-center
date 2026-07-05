<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

final class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($this->stripBasePath($uri));

        $route = $this->routes[$method][$path] ?? null;
        if ($route === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        if (!$this->runMiddlewares($route['middlewares'])) {
            return;
        }

        [$class, $action] = $route['handler'];
        $controller = new $class();
        $controller->{$action}();
    }

    private function addRoute(string $method, string $path, array $handler, array $middlewares): void
    {
        $this->routes[$method][$this->normalizePath($path)] = [
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    private function normalizePath(string $path): string
    {
        $clean = '/' . trim($path, '/');

        return $clean === '/' ? '/' : rtrim($clean, '/');
    }

    private function stripBasePath(string $uri): string
    {
        $base = rtrim((string) config('app.base_url', ''), '/');

        if ($base !== '' && str_starts_with($uri, $base)) {
            return substr($uri, strlen($base)) ?: '/';
        }

        return $uri;
    }

    private function runMiddlewares(array $middlewares): bool
    {
        foreach ($middlewares as $middleware) {
            if ($middleware === 'auth') {
                if (!AuthMiddleware::handle()) {
                    return false;
                }
                continue;
            }

            if (str_starts_with($middleware, 'role:')) {
                $role = trim(substr($middleware, strlen('role:')));
                if (!RoleMiddleware::handle($role)) {
                    return false;
                }
            }
        }

        return true;
    }
}
