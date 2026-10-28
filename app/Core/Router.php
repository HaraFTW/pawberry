<?php

declare(strict_types=1);

namespace PawBerry\Core;

use Closure;

final class Router
{
    /** @var list<array{methods: list<string>, pattern: string, regex: string, params: list<string>, handler: mixed, middleware: list<callable>}> */
    private array $routes = [];

    /** @param callable|array{0: class-string, 1: string} $handler */
    /** @param list<callable> $middleware */
    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add(['GET'], $pattern, $handler, $middleware);
    }

    /** @param callable|array{0: class-string, 1: string} $handler */
    /** @param list<callable> $middleware */
    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add(['POST'], $pattern, $handler, $middleware);
    }

    /** @param list<string> $methods */
    /** @param callable|array{0: class-string, 1: string} $handler */
    /** @param list<callable> $middleware */
    public function add(array $methods, string $pattern, callable|array $handler, array $middleware = []): void
    {
        $pattern = '/' . trim($pattern, '/');
        if ($pattern !== '/') {
            $pattern = rtrim($pattern, '/');
        }

        $paramNames = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m) use (&$paramNames): string {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'pattern' => $pattern,
            'regex' => $regex,
            'params' => $paramNames,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            $method = $request->method();
            // Treat HEAD as GET for route matching (response body omitted by clients).
            if ($method === 'HEAD') {
                $method = 'GET';
            }
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }

            array_shift($matches);
            $params = [];
            foreach ($route['params'] as $i => $name) {
                $params[$name] = $matches[$i] ?? '';
            }
            $request->setRouteParams($params);

            $handler = $this->resolveHandler($route['handler']);
            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                static function (callable $next, callable $middleware): Closure {
                    return static function (Request $req) use ($middleware, $next): Response {
                        return $middleware($req, $next);
                    };
                },
                $handler
            );

            return $pipeline($request);
        }

        return View::error(404, 'Page not found', 'That pawprint leads nowhere.');
    }

    /** @param callable|array{0: class-string, 1: string} $handler */
    private function resolveHandler(callable|array $handler): callable
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            return static function (Request $request) use ($controller, $method): Response {
                return $controller->$method($request);
            };
        }

        return $handler;
    }
}
