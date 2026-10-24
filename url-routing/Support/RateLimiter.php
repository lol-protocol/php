<?php

declare(strict_types=1);

namespace App\Support;

class RateLimiter
{
    /** A client's file this old belongs to a window that closed long ago (the longest one in use is a minute). */
    private const STALE_AFTER = 3600;

    /** How often a request looks for stale files; the marker file keeps that from being a directory scan per request. */
    private const SWEEP_EVERY = 600;

    private static ?RateLimiter $instance = null;
    private string $storePath;
    private string $sweepMarker;
    private int $retryAfter = 0;

    private function __construct()
    {
        $this->storePath = sys_get_temp_dir() . '/rate_limit_';
        // Not "rate_limit_...": the sweep must never take its own marker for a client's file.
        $this->sweepMarker = sys_get_temp_dir() . '/rate_limit-sweep';
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
                // The window frees a place when its oldest request falls out of it.
                $this->retryAfter = max(1, min($requests) + $windowSeconds - $now);
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

    /** Seconds until the client that was just refused may try again (0 if it was not refused). */
    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    public function checkLimit(int $maxRequests = 100, int $windowSeconds = 60): bool
    {
        $this->sweepIfDue();

        $ip = $this->getClientIp();
        $this->retryAfter = 0;
        if (!$this->isAllowed($ip, $maxRequests, $windowSeconds)) {
            http_response_code(429);
            if (!headers_sent()) {
                header('Retry-After: ' . $this->retryAfter);
            }
            return false;
        }
        return true;
    }

    /**
     * Deletes the files of clients that have not asked for an hour. Each client leaves one small file in the temp
     * directory and nothing else removes it, so on a public site they would pile up for as long as the system
     * keeps /tmp. Only files named like the ones this class writes are touched.
     *
     * @return int how many files it deleted
     */
    public function sweep(int $olderThanSeconds = self::STALE_AFTER): int
    {
        $limit = time() - $olderThanSeconds;
        $deleted = 0;

        foreach (glob($this->storePath . '*') ?: [] as $file) {
            if (!preg_match('/^rate_limit_[0-9a-f]{64}$/', basename($file))) {
                continue;
            }
            $modified = @filemtime($file);
            if ($modified !== false && $modified < $limit && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /** At most once every SWEEP_EVERY seconds, whichever request gets there first. */
    private function sweepIfDue(): void
    {
        $last = @filemtime($this->sweepMarker);
        if ($last !== false && time() - $last < self::SWEEP_EVERY) {
            return;
        }

        // Claim the sweep before doing it, so the requests that arrive meanwhile skip it. If the marker cannot
        // be written the files cannot either, and there is nothing to sweep.
        if (@touch($this->sweepMarker)) {
            $this->sweep();
        }
    }
}
