<?php

declare(strict_types=1);

class Router
{
    private array $routes = [];
    private string $currentGroup = '';

    public function get(string $path, array $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): self
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
        return $this;
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $pattern = $this->buildPattern($route['path']);
            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$controllerClass, $action] = $route['handler'];
                $controller = new $controllerClass();
                $controller->$action($params);
                return;
            }
        }

        http_response_code(404);
        $errorData = ['message' => 'Page non trouvée'];
        if (str_starts_with($path, '/api/')) {
            json_response($errorData, 404);
        }
        layout('errors/404', ['pageTitle' => '404 — Page non trouvée']);
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
