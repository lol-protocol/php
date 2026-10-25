<?php

declare(strict_types=1);

namespace App\Support;

class SessionManager
{
    private static ?SessionManager $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): SessionManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Resumes the session a request already has (it came with the session cookie) and does nothing for
     * a request that has none. A visitor who only reads pages must not get a cookie, nor leave a session
     * file on the server for every request: a crawler never sends the cookie back, so each of its hits
     * would be a new file. Whatever needs to write to the session starts it then (ensureStarted()).
     */
    public function resume(): void
    {
        if (isset($_COOKIE[session_name()])) {
            $this->start();
        }
    }

    /**
     * Starts a session if there is none, for the code that is about to write to it (a CSRF token, a flash
     * message, the cart, an owner who just logged in). Does nothing once output has begun, because a session
     * started then would lose its cookie, and does nothing on the bare command line: there is no request there,
     * and the unit tests fill $_SESSION by hand and should not leave session files behind. The tests' request
     * harness is the one command-line caller that serves requests, and it sets REQUEST_METHOD like a server.
     */
    public function ensureStarted(): void
    {
        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            return;
        }
        if (PHP_SAPI === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
            return;
        }

        $this->start();
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                // PHP would add its own Cache-Control, Expires and Pragma here, and only on requests that
                // start a session. Every response gets them from HttpSecurityHeaders instead, so a page
                // does not become cacheable just because its visitor has no cookie.
                'cache_limiter' => '',
                'cookie_httponly' => true,
                'cookie_secure' => $this->isSecure(),
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
                // PHP's garbage collector (where it runs: Debian and Ubuntu turn it off and clean with a cron
                // job that reads php.ini instead, see docs/ACCESO.md) must not delete the session of an owner
                // who has been idle for less than AccessPolicy::INACTIVIDAD_MAXIMA. The default is 24 minutes.
                'gc_maxlifetime' => AccessPolicy::INACTIVIDAD_MAXIMA,
            ]);
        }

        // Regenerate session ID on login (done by caller after auth)
        // For now, just log if session needs regeneration
        if (!isset($_SESSION['_session_started'])) {
            $_SESSION['_session_started'] = time();
        }
    }

    public function isValid(): bool
    {
        // Check session timeout (1 hour default)
        $timeout = 3600;
        if (isset($_SESSION['_session_started'])) {
            if (time() - $_SESSION['_session_started'] > $timeout) {
                $this->destroy();
                return false;
            }
        }

        return true;
    }

    public function regenerateId(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public function setCsrfToken(): string
    {
        $this->ensureStarted();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function getCsrfToken(): string|null
    {
        return $_SESSION['csrf_token'] ?? null;
    }

    public function validateCsrfToken(string $token): bool
    {
        $sessionToken = $this->getCsrfToken();
        if ($sessionToken === null) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    private function isSecure(): bool
    {
        return HttpSecurityHeaders::isHttps();
    }
}
