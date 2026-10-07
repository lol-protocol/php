<?php
// Router for the PHP built-in server used by tests/cache-texts.test.js.
// FIXTURE_DIR points at a folder holding law-page.html (so tests can edit it between runs).
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$dir = getenv('FIXTURE_DIR') ?: __DIR__;

switch ($path) {
    case '/law.html':
        $body = file_get_contents("$dir/law-page.html");
        $etag = '"' . md5($body) . '"';
        header("ETag: $etag");
        header('Last-Modified: Wed, 01 Jan 2025 00:00:00 GMT');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
        break;
    case '/redirect':
        header('Location: /law.html', true, 302);
        break;
    case '/plain.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo str_repeat("Linea de texto plano con contenido suficiente.\n", 12);
        break;
    case '/doc.pdf':
        header('Content-Type: application/pdf');
        echo '%PDF-1.4 binary';
        break;
    case '/empty.html':
        header('Content-Type: text/html');
        echo '<html><body><div id="app"></div><script>boot()</script></body></html>';
        break;
    case '/latin1.html':
        header('Content-Type: text/html; charset=iso-8859-1');
        echo mb_convert_encoding(
            '<html><body><main><h1>Protección de datos</h1><p>' . str_repeat('Información personal niño año. ', 20) . '</p></main></body></html>',
            'ISO-8859-1',
            'UTF-8'
        );
        break;
    case '/forbidden':
        http_response_code(403);
        echo 'no';
        break;
    default:
        http_response_code(404);
        echo 'not found';
}
