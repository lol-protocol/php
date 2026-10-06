<?php

declare(strict_types=1);

/**
 * Runs one request through the real front controller, in its own process
 * (index.php defines functions and calls exit, so it can't share PHPUnit's).
 *
 *   php tests/Support/request.php <host> <uri>
 *
 * The body goes to stdout; the status code is written to stderr as
 * "STATUS:<code>" on shutdown, so it's captured even when the app exits.
 */

[, $host, $uri] = $argv;

$_SERVER['HTTP_HOST'] = $host;
$_SERVER['REQUEST_URI'] = $uri;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PORT'] = '80';
// Unique per process so the rate limiter never throttles a test run.
$_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 254) . '-' . getmypid();

parse_str((string)parse_url($uri, PHP_URL_QUERY), $_GET);

register_shutdown_function(static function (): void {
    fwrite(STDERR, 'STATUS:' . (http_response_code() ?: 200)); // CLI reports false for the implicit 200
});

chdir(dirname(__DIR__, 2) . '/public');
require dirname(__DIR__, 2) . '/public/index.php';
