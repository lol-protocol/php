<?php

/**
 * Simple URL Router for handling friendly URLs
 * Supports genealogy and POS (Contrastocolor) site routing
 */

class Router
{
    protected $routes = [];
    protected $currentRequest = '';
    protected $currentMethod = '';
    protected $routeParams = [];

    public function __construct()
    {
        $this->currentRequest = $this->parseUrl($_SERVER['REQUEST_URI'] ?? '/');
        $this->currentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Register routes from configuration files
     */
    public function registerRoutes($configFile)
    {
        $routes = require $configFile;
        $this->routes = array_merge($this->routes, $routes);
    }

    /**
     * Parse the requested URL
     */
    protected function parseUrl($url)
    {
        // Remove base path if needed
        $basePath = getenv('APP_BASE_PATH') ?: '';
        if ($basePath && strpos($url, $basePath) === 0) {
            $url = substr($url, strlen($basePath));
        }

        // Remove query string
        $url = strtok($url, '?');

        // Ensure it starts with /
        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        // Remove trailing slash (optional)
        if ($url !== '/' && str_ends_with($url, '/')) {
            $url = rtrim($url, '/');
        }

        return $url;
    }

    /**
     * Match the requested URL against registered routes
     */
    public function matchRoute()
    {
        foreach ($this->routes as $name => $route) {
            if (!$this->methodMatches($route)) {
                continue;
            }

            $params = $this->matchPattern($route['path']);
            if ($params !== false) {
                $this->routeParams = $params;
                return [
                    'name' => $name,
                    'controller' => $route['controller'],
                    'params' => $params,
                    'route' => $route,
                ];
            }
        }

        return null;
    }

    /**
     * Check if HTTP method matches
     */
    protected function methodMatches($route)
    {
        $methods = $route['methods'] ?? ['GET'];
        return in_array($this->currentMethod, $methods);
    }

    /**
     * Match URL pattern against request
     */
    protected function matchPattern($pattern)
    {
        // Convert route pattern to regex
        $regexPattern = $this->patternToRegex($pattern);

        if (preg_match($regexPattern, $this->currentRequest . '/', $matches)) {
            // Extract named parameters
            $params = [];
            array_shift($matches); // Remove full match

            // Get parameter names from pattern
            preg_match_all('/{([a-zA-Z_][a-zA-Z0-9_]*)}/', $pattern, $paramNames);

            foreach ($paramNames[1] as $index => $name) {
                $params[$name] = $matches[$index + 1] ?? null;
            }

            return $params;
        }

        return false;
    }

    /**
     * Convert route pattern to regex
     */
    protected function patternToRegex($pattern)
    {
        $regex = preg_quote($pattern, '#');

        // Replace {param} with regex groups
        $regex = preg_replace_callback(
            '/{([a-zA-Z_][a-zA-Z0-9_]*)}/',
            function ($matches) {
                return '([a-z0-9-]+)';
            },
            $regex
        );

        return '#^' . $regex . '$#i';
    }

    /**
     * Get route parameters
     */
    public function getParams()
    {
        return $this->routeParams;
    }

    /**
     * Get parameter value
     */
    public function getParam($name, $default = null)
    {
        return $this->routeParams[$name] ?? $default;
    }

    /**
     * Generate URL for a route
     */
    public function route($name, $params = [])
    {
        if (!isset($this->routes[$name])) {
            return null;
        }

        $path = $this->routes[$name]['path'];

        // Replace parameters in path
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }

        return $path;
    }

    /**
     * Dispatch request to controller
     */
    public function dispatch()
    {
        $matched = $this->matchRoute();

        if (!$matched) {
            return $this->handleNotFound();
        }

        return $this->callController($matched);
    }

    /**
     * Call controller method
     */
    protected function callController($matched)
    {
        [$controllerClass, $method] = explode('@', $matched['controller']);

        // Convert to proper namespace
        $fullClass = 'App\\Controllers\\' . $controllerClass;

        if (!class_exists($fullClass)) {
            return $this->handleError("Controller not found: $fullClass");
        }

        $controller = new $fullClass();

        if (!method_exists($controller, $method)) {
            return $this->handleError("Method not found: {$method}");
        }

        return call_user_func_array(
            [$controller, $method],
            [$matched['params']]
        );
    }

    /**
     * Handle 404 errors
     */
    protected function handleNotFound()
    {
        http_response_code(404);
        return $this->renderError('404 - Page Not Found');
    }

    /**
     * Handle errors
     */
    protected function handleError($message)
    {
        http_response_code(500);
        return $this->renderError('500 - ' . $message);
    }

    /**
     * Render error page
     */
    protected function renderError($message)
    {
        echo "<h1>$message</h1>";
    }
}

/**
 * Helper function to generate URLs in templates
 */
function route($name, $params = [])
{
    global $router;
    return $router->route($name, $params);
}

/**
 * Helper function to get current route parameter
 */
function routeParam($name, $default = null)
{
    global $router;
    return $router->getParam($name, $default);
}
