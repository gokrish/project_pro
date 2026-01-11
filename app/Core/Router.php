<?php

namespace App\Core;

class Router
{
    private static array $routes = [];

    public static function get(string $path, array $handler): void
    {
        self::addRoute('GET', $path, $handler);
    }

    public static function post(string $path, array $handler): void
    {
        self::addRoute('POST', $path, $handler);
    }

    private static function addRoute(string $method, string $path, array $handler): void
    {
        // Convert route params {id} to regex
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        // Escape forward slashes
        $pattern = str_replace('/', '\/', $pattern);

        self::$routes[$method][$pattern] = $handler;
    }

    public function dispatch(string $uri, string $method): void
    {
        // Remove query string
        $uri = parse_url($uri, PHP_URL_PATH);
        // Remove trailing slash if not root
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = substr($uri, 0, -1);
        }

        foreach (self::$routes[$method] ?? [] as $pattern => $handler) {
            if (preg_match('/^' . $pattern . '$/', $uri, $matches)) {

                // Get named attributes from regex
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                [$controllerClass, $action] = $handler;

                if (!class_exists($controllerClass)) {
                    throw new \Exception("Controller class '$controllerClass' not found.");
                }

                $controller = new $controllerClass();

                if (!method_exists($controller, $action)) {
                    throw new \Exception("Action '$action' not found in controller '$controllerClass'.");
                }

                // Call the action with params
                call_user_func_array([$controller, $action], $params);
                return;
            }
        }

        $this->notFound();
    }

    private function notFound()
    {
        http_response_code(404);
        echo "<h1>404 Not Found</h1>";
    }
}
