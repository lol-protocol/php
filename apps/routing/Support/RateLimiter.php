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

    public function getClientIp(): string
    {
        $ip = $_SERVER['HTTP_CLIENT_IP'] ??
              $_SERVER['HTTP_X_FORWARDED_FOR'] ??
              $_SERVER['REMOTE_ADDR'] ??
              'unknown';

        // Handle multiple IPs in X-Forwarded-For
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }

        return $ip;
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
