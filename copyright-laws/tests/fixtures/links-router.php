<?php
// Router for the PHP built-in server used by tests/check-links.test.js.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
switch ($path) {
    case '/ok':
        echo 'fine';
        break;
    case '/head-refused':
        http_response_code($method === 'HEAD' ? 405 : 200);
        break;
    case '/moved':
        header('Location: /ok', true, 301);
        break;
    case '/loop': // a cookie wall: it redirects to itself for as long as the client sends no cookie
        header('Location: /loop', true, 302);
        break;
    case '/blocked':
        http_response_code(403);
        break;
    case '/error':
        http_response_code(500);
        break;
    default:
        http_response_code(404);
}
