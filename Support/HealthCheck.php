<?php

declare(strict_types=1);

namespace App\Support;

class HealthCheck
{
    public static function isHealthCheckRequest(string $uri): bool
    {
        return trim((string)strtok($uri, '?'), '/') === 'health';
    }

    public static function handle(): void
    {
        if (!self::isHealthCheckRequest($_SERVER['REQUEST_URI'] ?? '/')) {
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'timestamp' => time(),
        ]);
        exit;
    }
}
