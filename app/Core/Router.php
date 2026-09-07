<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Router a tabella.
 *
 * I pattern usano segnaposto tra graffe: '/dashboard/{blog}/posts/{uid}'.
 * Il segnaposto '{path:...}' cattura tutto il resto del percorso, comprese le
 * barre, e serve alla rotta jolly che risolve gli slug dei post.
 */
final class Router
{
    /** @var list<array{methods:list<string>,regex:string,params:list<string>,handler:mixed}> */
    private array $routes = [];

    /** @var callable|null */
    private $fallback = null;

    public function get(string $pattern, mixed $handler): self
    {
        return $this->map(['GET', 'HEAD'], $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): self
    {
        return $this->map(['POST'], $pattern, $handler);
    }

    public function any(string $pattern, mixed $handler): self
    {
        return $this->map(['GET', 'HEAD', 'POST'], $pattern, $handler);
    }

    /** @param list<string> $methods */
    public function map(array $methods, string $pattern, mixed $handler): self
    {
        [$regex, $params] = self::compile($pattern);
        $this->routes[] = [
            'methods' => $methods,
            'regex'   => $regex,
            'params'  => $params,
            'handler' => $handler,
        ];
        return $this;
    }

    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }

    /**
     * Trova la rotta corrispondente e ne esegue l'handler.
     *
     * @param array<string,mixed> $context Argomenti passati prima dei parametri di rotta.
     */
    public function dispatch(Request $request, array $context = []): Response
    {
        $path = '/' . trim($request->path, '/');
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if (!in_array($request->method, $route['methods'], true)) {
                $methodMismatch = true;
                continue;
            }

            $params = [];
            foreach ($route['params'] as $name) {
                $params[$name] = isset($matches[$name]) ? rawurldecode($matches[$name]) : '';
            }

            return $this->invoke($route['handler'], $request, $context, $params);
        }

        if ($methodMismatch) {
            return Response::text(__('error.method_not_allowed'), 405)
                ->withHeader('Allow', 'GET, POST');
        }

        if ($this->fallback !== null) {
            return ($this->fallback)($request, $context);
        }

        return Response::text(__('error.not_found'), 404);
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,string> $params
     */
    private function invoke(mixed $handler, Request $request, array $context, array $params): Response
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class($request, ...array_values($context));
            $result = $instance->$method(...array_values($params));
        } else {
            $result = $handler($request, $context, $params);
        }

        if (!$result instanceof Response) {
            throw new \LogicException('Un handler di rotta deve restituire una Response.');
        }
        return $result;
    }

    /** @return array{0:string,1:list<string>} */
    private static function compile(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{(\w+)(?::(\w+))?\}/',
            static function (array $m) use (&$params): string {
                $name = $m[1];
                $type = $m[2] ?? null;
                $params[] = $name;
                // 'path' cattura anche le barre: serve agli slug annidati.
                $charset = $name === 'path' || $type === 'path' ? '.+' : '[^/]+';
                return '(?P<' . $name . '>' . $charset . ')';
            },
            $pattern
        ) ?? $pattern;

        // La barra finale è sempre opzionale, per non duplicare gli URL.
        return ['#^' . rtrim($regex, '/') . '/?$#u', $params];
    }
}
