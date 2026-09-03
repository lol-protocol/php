<?php

declare(strict_types=1);

namespace App;

final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function add(string $page, callable $handler): void
    {
        $this->routes[$page] = $handler;
    }

    public function dispatch(string $page): void
    {
        $handler = $this->routes[$page] ?? null;
        if ($handler === null) {
            http_response_code(404);
            echo '404 - pagina no encontrada';
            return;
        }
        $handler();
    }
}
