<?php
/**
 * HTTP Router
 * Handles URL routing and dispatching to controllers
 */

declare(strict_types=1);

namespace Core;

class Router
{
    private array $routes = [];
    private array $middlewares = [];
    
    /**
     * Add a GET route
     */
    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }
    
    /**
     * Add a POST route
     */
    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }
    
    /**
     * Add a PUT route
     */
    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }
    
    /**
     * Add a DELETE route
     */
    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }
    
    /**
     * Add a route for any HTTP method
     */
    public function any(string $path, callable|array $handler, array $middleware = []): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
    }
    
    /**
     * Add a route group with common middleware
     */
    public function group(array $middleware, callable $callback): void
    {
        $previousMiddlewares = $this->middlewares;
        $this->middlewares = array_merge($this->middlewares, $middleware);
        
        $callback($this);
        
        $this->middlewares = $previousMiddlewares;
    }
    
    /**
     * Add a route
     */
    private function addRoute(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        $pattern = $this->convertToRegex($path);
        $allMiddleware = array_merge($this->middlewares, $middleware);
        
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $allMiddleware
        ];
    }
    
    /**
     * Convert route path to regex pattern
     */
    private function convertToRegex(string $path): string
    {
        // Convert {param} to named capture groups
        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $path);
        // Escape forward slashes
        $pattern = str_replace('/', '\/', $pattern);
        // Add anchors
        return '/^' . $pattern . '$/';
    }
    
    /**
     * Dispatch the request
     */
    public function dispatch(Request $request, Response $response): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $path, $matches)) {
                // Extract route parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->setRouteParams($params);
                
                // Execute middleware
                foreach ($route['middleware'] as $middleware) {
                    if (is_string($middleware)) {
                        $middlewareClass = "App\\Middleware\\{$middleware}";
                        if (class_exists($middlewareClass)) {
                            $middlewareInstance = new $middlewareClass();
                            if (!$middlewareInstance->handle($request, $response)) {
                                return; // Middleware stopped execution
                            }
                        }
                    } elseif (is_callable($middleware)) {
                        if (!$middleware($request, $response)) {
                            return;
                        }
                    }
                }
                
                // Execute handler
                if (is_array($route['handler'])) {
                    [$controllerClass, $method] = $route['handler'];
                    
                    if (!class_exists($controllerClass)) {
                        throw new \Exception("Controller {$controllerClass} not found");
                    }
                    
                    $controller = new $controllerClass();
                    
                    if (!method_exists($controller, $method)) {
                        throw new \Exception("Method {$method} not found in {$controllerClass}");
                    }
                    
                    $controller->$method($request, $response);
                } elseif (is_callable($route['handler'])) {
                    $route['handler']($request, $response);
                }
                
                return;
            }
        }
        
        // No route found
        $response->setStatusCode(404);
        $response->setContent('<h1>404 - Page Not Found</h1>');
        $response->send();
    }
    
    /**
     * Generate URL for named route
     */
    public function url(string $name, array $params = []): string
    {
        // This would be implemented for named routes
        // For now, return a simple implementation
        return '/' . ltrim($name, '/');
    }
}
