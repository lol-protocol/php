<?php

// Endpoint de moderación de chat: POST {"text": "…", "language": "spa"} → JSON con
// la decisión (approve, review o reject). Ver «Endpoint HTTP» en el README.
// Publica sólo este archivo (o esta carpeta): config/, src/ y vendor/ quedan fuera.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\Http\{ModerationEndpoint, RateLimiter};

ini_set('display_errors', '0'); // un error nunca devuelve rutas ni trazas: va al log y el cliente recibe un 500 genérico

// Peticiones por minuto y por IP (MODERAR_POR_MINUTO, 30 por omisión; 0 lo apaga). Detrás de un
// proxy propio que escriba X-Forwarded-For, MODERAR_CONFIAR_X_FORWARDED_FOR=1 usa su último valor.
$porMinuto = (int) (getenv('MODERAR_POR_MINUTO') === false ? 30 : getenv('MODERAR_POR_MINUTO'));
$cliente = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (getenv('MODERAR_CONFIAR_X_FORWARDED_FOR') === '1' && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $cliente = trim((string) array_slice(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']), -1)[0]);
}

try {
    $limite = $porMinuto > 0 ? new RateLimiter(getenv('MODERAR_LIMITES_DIR') ?: sys_get_temp_dir() . '/moderar-limites') : null;
    if ($limite !== null && !$limite->attempt($cliente, $porMinuto, 60, time())) {
        $response = ['status' => 429, 'headers' => ['Retry-After' => (string) $limite->retryAfter($cliente, 60, time())],
            'body' => ['error' => ['code' => 'demasiadas_peticiones', 'message' => 'Demasiadas peticiones seguidas. Espera un momento.']]];
    }
    // Se lee un byte más del límite: basta para saber que lo supera sin cargar todo el cuerpo.
    $body = (string) file_get_contents('php://input', false, null, 0, ModerationEndpoint::MAX_BODY_BYTES + 1);
    $response ??= (new ModerationEndpoint(__DIR__ . '/../config'))
        ->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $body, $_SERVER['CONTENT_TYPE'] ?? '');
} catch (Throwable $e) {
    error_log('moderar.php: ' . $e);
    $response = ['status' => 500, 'headers' => [], 'body' => ['error' => ['code' => 'error_interno', 'message' => 'No se pudo revisar el texto.']]];
}

http_response_code($response['status']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
foreach ($response['headers'] as $name => $value) {
    header("$name: $value");
}
echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
