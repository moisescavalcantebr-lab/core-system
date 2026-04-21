<?php

class Router
{
    private array $routes = [];

    public function get(string $uri, callable $action)
    {
        $this->routes['GET'][$uri] = $action;
    }

    public function post(string $uri, callable $action)
    {
        $this->routes['POST'][$uri] = $action;
    }

    public function dispatch(string $method, string $uri)
    {
        $uri = parse_url($uri, PHP_URL_PATH);

        if (isset($this->routes[$method][$uri])) {
            return call_user_func($this->routes[$method][$uri]);
        }

        http_response_code(404);
        echo "404 - Rota não encontrada";
    }
}