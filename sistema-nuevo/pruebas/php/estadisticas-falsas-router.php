<?php

declare(strict_types=1);

// Router de "php -S" que hace de servicio de estadísticas con respuestas fijas: lo levanta
// linea-tiempo-cohortes-test.php. Anota cada pedido (su query string) en el archivo de la
// variable ESTADISTICAS_FALSAS_LOG, para que la prueba vea qué filtros llegaron.
file_put_contents((string) getenv('ESTADISTICAS_FALSAS_LOG'), ($_SERVER['QUERY_STRING'] ?? '') . "\n", FILE_APPEND);

$cohortes = [
    'payment' => [
        'type' => 'payment', 'count' => 10,
        'avg_duration_ms' => 1000.0, 'median_duration_ms' => 900.0, 'p90_duration_ms' => 2000.0,
        'avg_amount_usd' => 50.0, 'median_amount_usd' => 40.0, 'p90_amount_usd' => 120.0, 'currency' => 'USD',
    ],
    'login' => [
        'type' => 'login', 'count' => 25,
        'avg_duration_ms' => 200.0, 'median_duration_ms' => 180.0, 'p90_duration_ms' => 350.0,
        'avg_amount_usd' => null, 'median_amount_usd' => null, 'p90_amount_usd' => null, 'currency' => 'USD',
    ],
    // Lo que devuelve el servicio real cuando el universo no tiene ninguna acción de ese tipo.
    'vacio' => [
        'type' => 'vacio', 'count' => 0,
        'avg_duration_ms' => 0.0, 'median_duration_ms' => null, 'p90_duration_ms' => null,
        'avg_amount_usd' => null, 'median_amount_usd' => null, 'p90_amount_usd' => null, 'currency' => 'USD',
    ],
];

$tipo = $_GET['type'] ?? '';
if (!isset($cohortes[$tipo])) {
    http_response_code(500);
    echo '{"error":"tipo desconocido"}';
    return;
}
header('Content-Type: application/json');
echo json_encode($cohortes[$tipo]);
