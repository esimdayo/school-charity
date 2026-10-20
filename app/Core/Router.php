<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $action): void
    {
        $this->routes[] = ['GET', $path, $action];
    }

    public function post(string $path, array $action): void
    {
        $this->routes[] = ['POST', $path, $action];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        if ($path !== '/') $path = rtrim($path, '/');

        foreach ($this->routes as [$routeMethod, $routePath, [$class, $fn]]) {
            $pattern = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $routePath) . '$#';
            if ($routeMethod === $method && preg_match($pattern, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                (new $class())->$fn(...array_values($params));
                return;
            }
        }
        http_response_code(404);
        View::render('message', ['title' => Lang::t('not_found'), 'text' => Lang::t('not_found')]);
    }
}
