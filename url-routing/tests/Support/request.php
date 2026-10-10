<?php

declare(strict_types=1);

/**
 * Runs one request through the real front controller, in its own process
 * (index.php defines functions and calls exit, so it can't share PHPUnit's).
 *
 *   php tests/Support/request.php <host> <uri> [<method> [<postBody> [<sessionId> [<authorization>]]]]
 *
 * <postBody> is a urlencoded query string (e.g. "sku=X&cantidad=2"), used
 * only when <method> is POST. <sessionId>, when given, is reused as the PHP
 * session id so a later call can see session state a previous call wrote
 * (e.g. a cart) — PHP's default session storage is a file under the system
 * temp dir, shared across these per-request processes. <authorization>, when
 * given, is sent as the request's Authorization header (see AccessPolicy).
 *
 * The body goes to stdout; the status code and session id are written to
 * stderr on shutdown (so they're captured even when the app exits), as
 * "SESSION:<id>" followed by "STATUS:<code>" on the last line. Response
 * headers aren't observable here: the CLI SAPI has no real HTTP response,
 * so header() is a no-op and headers_list() is always empty.
 *
 * <sessionId> is only a HINT: SessionManager starts sessions with
 * use_strict_mode, which silently ignores a client-supplied id that isn't
 * already a known session and assigns a fresh one instead — exactly as a
 * real browser presenting a forged cookie would get a new session, not the
 * one it asked for. Read back the actual id from the "SESSION:" line and
 * pass *that* to the next call to keep reusing the same session.
 */

[, $host, $uri] = $argv;
$method = $argv[3] ?? 'GET';
$postBody = $argv[4] ?? '';
$sessionId = $argv[5] ?? null;
$autorizacion = ($argv[6] ?? '') !== '' ? $argv[6] : null;

if ($sessionId !== null) {
    session_id($sessionId);
}

$_SERVER['HTTP_HOST'] = $host;
$_SERVER['REQUEST_URI'] = $uri;
$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['SERVER_PORT'] = '80';
if ($autorizacion !== null) {
    $_SERVER['HTTP_AUTHORIZATION'] = $autorizacion;
}
// Unique per process so the rate limiter never throttles a test run.
$_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 254) . '-' . getmypid();

parse_str((string)parse_url($uri, PHP_URL_QUERY), $_GET);
if ($method === 'POST') {
    parse_str($postBody, $_POST);
}

register_shutdown_function(static function (): void {
    fwrite(STDERR, 'SESSION:' . session_id() . "\n");
    fwrite(STDERR, 'STATUS:' . (http_response_code() ?: 200)); // CLI reports false for the implicit 200
});

chdir(dirname(__DIR__, 2) . '/public');
require dirname(__DIR__, 2) . '/public/index.php';
