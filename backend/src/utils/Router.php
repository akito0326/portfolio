<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

/**
 * 依存ライブラリを使わない最小限のルーター。
 * パターン例: /api/projects/{id}
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * @param array<string, string> $params
     */
    public function dispatch(string $method, string $uri): void
    {
        $path = self::normalizePath($uri);
        $method = strtoupper($method);
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match(self::toRegex($route['pattern']), $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];

                continue;
            }

            /** @var array<string, string> $params */
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            ($route['handler'])($params);

            return;
        }

        if ($allowedMethods !== []) {
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            }

            throw new HttpException(405, 'method_not_allowed', $path . ' は ' . $method . ' に対応していません。');
        }

        throw new HttpException(404, 'route_not_found', $path . ' は存在しません。');
    }

    private static function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        // 末尾スラッシュを無視する
        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    private static function toRegex(string $pattern): string
    {
        $segments = array_map(
            static function (string $segment): string {
                // {id} のようなプレースホルダを名前付きキャプチャへ変換する
                if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $matches) === 1) {
                    return '(?P<' . $matches[1] . '>[^/]+)';
                }

                return preg_quote($segment, '#');
            },
            explode('/', $pattern),
        );

        return '#^' . implode('/', $segments) . '$#';
    }
}
