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

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => $this->isSecure(),
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
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
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] === 443;
    }
}
