<?php

/**
 * Punto de entrada. Con el servidor de PHP:
 *     php -S localhost:8080 -t public public/index.php
 * Con nginx o Apache, dirige todas las peticiones que no sean archivos de public/ a este archivo.
 */

require dirname(__DIR__) . '/bootstrap.php';

use MarcalibrosApp\Application;
use MarcalibrosApp\Config;
use MarcalibrosApp\Request;

// El servidor integrado de PHP entrega solo los archivos estáticos que existen.
if (PHP_SAPI === 'cli-server') {
    $requested = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_string($requested) && str_starts_with($requested, '/assets/') && is_file(__DIR__ . $requested)) {
        return false;
    }
}

$config = Config::fromEnvironment(dirname(__DIR__), getenv() ?: []);
$request = Request::fromGlobals($config, $_SERVER, $_GET, $_POST, $_FILES);

Application::create($config, dirname(__DIR__))->handle($request)->send($request->method === 'HEAD');
