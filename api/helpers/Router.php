<?php

/**
 * Lightweight request router with path parameter support.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'path'    => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];

        // Strip query string
        $uri = strtok($uri, '?');

        // Strip /api prefix
        $uri = preg_replace('#^/api#', '', $uri);

        // Normalize: ensure leading slash, strip trailing slash (except root)
        if ($uri === '' || $uri === '/') {
            $uri = '/';
        } else {
            $uri = '/' . trim($uri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPath($route['path'], $uri);
            if ($params !== false) {
                // Parse JSON body for POST/PUT
                if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                    $rawBody = file_get_contents('php://input');
                    $body = json_decode($rawBody, true);
                    $params['_body'] = is_array($body) ? $body : [];
                } else {
                    $params['_body'] = [];
                }

                call_user_func($route['handler'], $params);
                return;
            }
        }

        Response::notFound('Route not found');
    }

    /**
     * Match a route path pattern against a URI.
     * Returns extracted parameters array on match, or false.
     */
    private function matchPath(string $pattern, string $uri): array|false
    {
        // Convert pattern to regex
        $patternParts = explode('/', trim($pattern, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        // Handle root path
        if ($pattern === '/' && $uri === '/') {
            return [];
        }

        if (count($patternParts) !== count($uriParts)) {
            return false;
        }

        $params = [];

        for ($i = 0; $i < count($patternParts); $i++) {
            $pp = $patternParts[$i];
            $up = $uriParts[$i];

            if (preg_match('/^\{(\w+)\}$/', $pp, $m)) {
                // Path parameter
                $params[$m[1]] = urldecode($up);
            } elseif ($pp !== $up) {
                return false;
            }
        }

        return $params;
    }
}
