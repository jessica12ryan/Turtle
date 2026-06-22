<?php

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, string $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, string $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, string $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, string $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, string $handler, array $middleware): void
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
            'original' => $path,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($route['middleware'] as $mw) {
                    $this->runMiddleware($mw);
                }

                [$controllerClass, $method] = explode('@', $route['handler']);
                $controllerClass = 'App\\Controllers\\' . $controllerClass;

                if (!class_exists($controllerClass)) {
                    throw new \Exception("Controller {$controllerClass} not found");
                }

                $controller = new $controllerClass();
                $controller->$method(...array_values($params));
                return;
            }
        }

        http_response_code(404);
        require base_path('www/Views/errors/404.php');
    }

    private function runMiddleware(string $middleware): void
    {
        $auth = Auth::instance();

        switch ($middleware) {
            case 'auth':
                if (!$auth->check()) {
                    $_SESSION['_flash']['error'] = 'Please log in to continue.';
                    redirect('/login');
                }
                $user = $auth->user();
                if (!empty($user['timezone'])) {
                    date_default_timezone_set($user['timezone']);
                }
                break;
            case 'guest':
                if ($auth->check()) {
                    redirect('/home');
                }
                break;
            case 'role:admin':
                if (!$auth->check() || $auth->user()['role'] !== 'admin') {
                    http_response_code(403);
                    require base_path('www/Views/errors/403.php');
                    exit;
                }
                break;
            case 'role:admin,landlord':
                if (!$auth->check() || !in_array($auth->user()['role'], ['admin', 'landlord'])) {
                    http_response_code(403);
                    require base_path('www/Views/errors/403.php');
                    exit;
                }
                break;
            case 'role:landlord':
                if (!$auth->check() || !in_array($auth->user()['role'], ['admin', 'landlord'])) {
                    http_response_code(403);
                    require base_path('www/Views/errors/403.php');
                    exit;
                }
                break;
            case 'role:landlord,property_manager':
                if (!$auth->check() || !in_array($auth->user()['role'], ['admin', 'landlord', 'property_manager'])) {
                    http_response_code(403);
                    require base_path('www/Views/errors/403.php');
                    exit;
                }
                break;
            case 'role:admin,landlord,property_manager':
                if (!$auth->check() || !in_array($auth->user()['role'], ['admin', 'landlord', 'property_manager'])) {
                    http_response_code(403);
                    require base_path('www/Views/errors/403.php');
                    exit;
                }
                break;
            case 'role:tenant':
                if (!$auth->check() || $auth->user()['role'] !== 'tenant') {
                    http_response_code(403);
                    require base_path('www/Views/errors/403.php');
                    exit;
                }
                break;
            case 'role:staff':
                if (!$auth->check() || !in_array($auth->user()['role'], ['admin', 'landlord', 'property_manager', 'maintenance'])) {
                    http_response_code(403);
                    require base_path('www/Views/errors/403.php');
                    exit;
                }
                break;
            default:
                if (str_starts_with($middleware, 'perm:')) {
                    $perm = substr($middleware, 5);
                    if (!can($perm)) {
                        http_response_code(403);
                        require base_path('www/Views/errors/403.php');
                        exit;
                    }
                }
                break;
        }
    }
}
