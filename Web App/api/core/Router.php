<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * Router.php — Lightweight RESTful API Router
 * ─────────────────────────────────────────────────────────────────────────────
 * รองรับ Method GET, POST, PUT, DELETE, OPTIONS
 * รองรับ Path Parameters เช่น /products/{id}, /orders/{id}
 * ─────────────────────────────────────────────────────────────────────────────
 */

class Router
{
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

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => trim($pattern, '/'),
            'handler' => $handler
        ];
    }

    /**
     * ค้นหา Route ที่ตรงกับ Method + URI แล้วเรียก Controller Action
     */
    public function dispatch(string $method, string $uri): void
    {
        // ตัด Query string ออกก่อนนำไปเทียบกับ route pattern
        $cleanUri = explode('?', $uri)[0];
        $cleanUri = trim($cleanUri, '/');
        $method   = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPattern($route['pattern'], $cleanUri);
            if ($params !== false) {
                call_user_func_array($route['handler'], $params);
                return;
            }
        }

        Response::notFound("ไม่พบ Endpoint: [{$method}] /{$cleanUri}");
    }

    /**
     * เทียบ route pattern เช่น "products/{id}" กับ uri จริง เช่น "products/5"
     */
    private function matchPattern(string $pattern, string $uri)
    {
        $patternSegments = $pattern === '' ? [] : explode('/', $pattern);
        $uriSegments     = $uri === '' ? [] : explode('/', $uri);

        if (count($patternSegments) !== count($uriSegments)) {
            return false;
        }

        $params = [];
        foreach ($patternSegments as $i => $segment) {
            if (preg_match('/^\{(\w+)\}$/', $segment, $matches)) {
                $params[] = $uriSegments[$i];
            } elseif ($segment !== $uriSegments[$i]) {
                return false;
            }
        }

        return $params;
    }
}
