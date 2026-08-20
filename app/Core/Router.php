<?php

namespace App\Core;

/**
 * 简单路由器
 */
class Router
{
    private $routes = [];
    private $middleware = [];

    public function get($path, $handler, $middleware = [])
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = [])
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put($path, $handler, $middleware = [])
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete($path, $handler, $middleware = [])
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute($method, $path, $handler, $middleware = [])
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch($method, $uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = rtrim($path, '/');

        if (empty($path)) {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->convertToRegex($route['path']);
            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // 执行中间件
                foreach ($route['middleware'] as $mw) {
                    $middlewareClass = $this->normalizeClassName($mw, 'App\\Middleware\\');
                    $this->ensureClassLoaded($middlewareClass, APP_PATH . '/Middleware');
                    $middleware = new $middlewareClass();
                    $middleware->handle($params);
                }

                // 执行处理器
                if (is_callable($route['handler'])) {
                    return call_user_func($route['handler'], $params);
                }

                if (is_array($route['handler'])) {
                    [$controller, $method] = $route['handler'];
                    $controllerClass = $this->normalizeClassName($controller, 'App\\Controllers\\');
                    $this->ensureClassLoaded($controllerClass, APP_PATH . '/Controllers');
                    $instance = new $controllerClass();
                    return $instance->$method($params);
                }

                throw new \Exception("无效的路由处理器");
            }
        }

        http_response_code(404);
        if ($this->isApiRequest($path)) {
            echo json_encode(['success' => false, 'message' => '接口不存在']);
        } else {
            $this->show404();
        }
    }

    private function convertToRegex($path)
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function normalizeClassName($class, $namespacePrefix)
    {
        $class = ltrim($class, '\\');
        $prefix = trim($namespacePrefix, '\\');

        if (strpos($class, $prefix . '\\') === 0) {
            return $class;
        }

        return $prefix . '\\' . $class;
    }

    private function ensureClassLoaded($className, $baseDir)
    {
        if (class_exists($className, false)) {
            return;
        }

        $relative = substr($className, strlen('App\\'));
        $file = rtrim($baseDir, '/') . '/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    private function isApiRequest($path)
    {
        return strpos($path, '/api/') === 0;
    }

    private function show404()
    {
        http_response_code(404);
        $content = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">';
        $content .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $content .= '<title>404 - 页面不存在</title>';
        $content .= '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
        $content .= '</head><body class="bg-light">';
        $content .= '<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh">';
        $content .= '<div class="text-center">';
        $content .= '<h1 class="display-1 text-muted">404</h1>';
        $content .= '<p class="lead">页面不存在</p>';
        $content .= '<a href="/" class="btn btn-primary">返回首页</a>';
        $content .= '</div></div></body></html>';
        echo $content;
    }
}
