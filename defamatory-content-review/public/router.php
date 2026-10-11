<?php

// Servidor de la demo, sólo para desarrollo:
//   php -S localhost:8000 public/router.php   →   http://localhost:8000/
// Sirve la página, el endpoint y el JS del front; nada más del paquete queda expuesto.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$files = [
    '/' => [__DIR__ . '/demo.html', 'text/html'],
    '/limit-repeated-letters.js' => [dirname(__DIR__) . '/js/limit-repeated-letters.js', 'text/javascript'],
];

if ($path === '/moderar') {
    require __DIR__ . '/moderar.php';
} elseif (isset($files[$path])) {
    header('Content-Type: ' . $files[$path][1] . '; charset=utf-8');
    readfile($files[$path][0]);
} else {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "No encontrado\n";
}

return true;
