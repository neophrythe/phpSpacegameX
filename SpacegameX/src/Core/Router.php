<?php
namespace Core;

class Router {
    protected $routes = [];

    public function addRoute($method, $uri, $controllerAction) {
        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $controllerAction
        ];
    }

    public function dispatch($uri, $method) {
        foreach ($this->routes as $route) {
            // Simple matching, needs improvement for parameters and regex
            if ($route['uri'] === $uri && $route['method'] === strtoupper($method)) {
                list($controller, $action) = explode('@', $route['action']);
                $controller = "Controllers\\" . $controller; // Assuming controllers are in App\Controllers
                
                if (class_exists($controller)) {
                    $controllerInstance = new $controller();
                    if (method_exists($controllerInstance, $action)) {
                        return $controllerInstance->$action();
                    }
                }
            }
        }
        // Handle 404
        http_response_code(404);
        echo "404 Not Found - Route: " . htmlspecialchars($uri);
        return null;
    }
}
?>
