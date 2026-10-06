<?php

declare(strict_types=1);

namespace App\Support;

class RateLimiter
{
    private static ?RateLimiter $instance = null;
    private string $storePath;

    private function __construct()
    {
        $this->storePath = sys_get_temp_dir() . '/rate_limit_';
    }

    public static function getInstance(): RateLimiter
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isAllowed(string $ip, int $maxRequests = 100, int $windowSeconds = 60): bool
    {
        $file = $this->storePath . hash('sha256', $ip);
        $now = time();
        $windowStart = $now - $windowSeconds;

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            // Fail open: an unreadable store must not block legitimate traffic.
            return true;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return true;
            }

            $data = stream_get_contents($handle);
            $requests = array_filter(
                $data !== false ? (json_decode($data, true) ?: []) : [],
                fn($timestamp) => $timestamp > $windowStart
            );

            if (count($requests) >= $maxRequests) {
                return false;
            }

            $requests[] = $now;

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode(array_values($requests)));
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Client-supplied headers (X-Forwarded-For, Client-IP) are only honored
     * when the direct peer is a proxy listed in TRUSTED_PROXIES; otherwise any
     * client could send a fresh fake IP per request and never hit the limit.
     */
    public function getClientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $trusted = $this->trustedProxies();

        if (!in_array($remote, $trusted, true)) {
            return $remote;
        }

        $forwarded = array_map('trim', explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));

        // Walk right to left: the rightmost hop not added by one of our own
        // proxies is the first address we can't have been lied to about.
        foreach (array_reverse($forwarded) as $ip) {
            if ($ip !== '' && !in_array($ip, $trusted, true)) {
                return $ip;
            }
        }

        return $remote;
    }

    /** @return string[] */
    private function trustedProxies(): array
    {
        $raw = (string)getenv('TRUSTED_PROXIES');
        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn($ip) => $ip !== ''));
    }

    public function checkLimit(int $maxRequests = 100, int $windowSeconds = 60): bool
    {
        $ip = $this->getClientIp();
        if (!$this->isAllowed($ip, $maxRequests, $windowSeconds)) {
            http_response_code(429);
            return false;
        }
        return true;
    }
}
