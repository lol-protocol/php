<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Two probes, for two different questions.
 *
 *  - /health: is PHP answering? It runs before anything else and never touches the database, so it stays
 *    "ok" while the database is down (which is what a liveness probe wants).
 *  - /ready: can this site serve? It asks the site's database (the Host picks which) and whether every
 *    migration has been applied, which is what catches a deploy that forgot `bin/migrate.php`. It answers
 *    503 with a body that says which of the two is wrong and nothing more; the detail goes to the log.
 */
class HealthCheck
{
    public static function isHealthCheckRequest(string $uri): bool
    {
        return self::path($uri) === 'health';
    }

    public static function isReadinessRequest(string $uri): bool
    {
        return self::path($uri) === 'ready';
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

    /**
     * What /ready answers for this database. Pure: the HTTP part is handleReady().
     *
     * @return array{status: int, body: array{status: string, db: string, migrations: string}, detail: ?string}
     *         `detail` is the reason for the log when something is wrong, null when all is well.
     */
    public static function readiness(Database $db, Migrator $migrator): array
    {
        try {
            $db->fetchOne('SELECT 1 AS ok');
        } catch (\Throwable $e) {
            return [
                'status' => 503,
                'body' => ['status' => 'error', 'db' => 'down', 'migrations' => 'unknown'],
                'detail' => 'database: ' . $e->getMessage(),
            ];
        }

        try {
            $pending = $migrator->pending();
        } catch (\Throwable $e) {
            return [
                'status' => 503,
                'body' => ['status' => 'error', 'db' => 'ok', 'migrations' => 'unknown'],
                'detail' => 'migrations: ' . $e->getMessage(),
            ];
        }

        if ($pending !== []) {
            return [
                'status' => 503,
                'body' => ['status' => 'error', 'db' => 'ok', 'migrations' => 'pending'],
                'detail' => 'migrations pending: ' . implode(', ', $pending),
            ];
        }

        return [
            'status' => 200,
            'body' => ['status' => 'ok', 'db' => 'ok', 'migrations' => 'applied'],
            'detail' => null,
        ];
    }

    public static function handleReady(Database $db, Migrator $migrator, Logger $logger): void
    {
        if (!self::isReadinessRequest($_SERVER['REQUEST_URI'] ?? '/')) {
            return;
        }

        $result = self::readiness($db, $migrator);
        if ($result['detail'] !== null) {
            $logger->error('Readiness check failed', ['detail' => $result['detail']]);
        }

        // A probe is not a visitor: no session cookie, and nothing for a cache to keep.
        header_remove('Set-Cookie');
        http_response_code($result['status']);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($result['body'] + ['timestamp' => time()]);
        exit;
    }

    private static function path(string $uri): string
    {
        return trim((string)strtok($uri, '?'), '/');
    }
}
