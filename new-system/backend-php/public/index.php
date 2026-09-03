<?php

declare(strict_types=1);

require __DIR__ . '/../src/DataStore.php';
require __DIR__ . '/../src/StatsClient.php';
require __DIR__ . '/../src/Api.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

try {
    if ($path === '/api/users') {
        api_users();
    } elseif ($path === '/api/groups') {
        api_groups();
    } elseif ($path === '/api/timeline') {
        api_timeline();
    } else {
        api_not_found();
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
