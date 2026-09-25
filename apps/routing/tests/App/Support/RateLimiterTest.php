<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    /** @var string[] */
    private array $filesToClean = [];

    protected function tearDown(): void
    {
        foreach ($this->filesToClean as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->filesToClean = [];
    }

    private function uniqueIp(): string
    {
        $ip = '10.0.0.' . random_int(1, 254) . '-' . uniqid();
        $this->filesToClean[] = sys_get_temp_dir() . '/rate_limit_' . hash('sha256', $ip);
        return $ip;
    }

    public function testAllowsRequestsUnderLimit(): void
    {
        $limiter = RateLimiter::getInstance();
        $ip = $this->uniqueIp();

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($limiter->isAllowed($ip, 5, 60));
        }
    }

    public function testBlocksRequestsOverLimit(): void
    {
        $limiter = RateLimiter::getInstance();
        $ip = $this->uniqueIp();

        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($limiter->isAllowed($ip, 3, 60));
        }

        $this->assertFalse($limiter->isAllowed($ip, 3, 60));
    }

    public function testWindowResetsAfterExpiry(): void
    {
        $limiter = RateLimiter::getInstance();
        $ip = $this->uniqueIp();

        $this->assertTrue($limiter->isAllowed($ip, 1, 60));
        $this->assertFalse($limiter->isAllowed($ip, 1, 60));

        // A window that has already elapsed (0 seconds) behaves as expired,
        // so the next request should be allowed again.
        $this->assertTrue($limiter->isAllowed($ip, 1, 0));
    }

    public function testDifferentIpsAreTrackedIndependently(): void
    {
        $limiter = RateLimiter::getInstance();
        $ipA = $this->uniqueIp();
        $ipB = $this->uniqueIp();

        $this->assertTrue($limiter->isAllowed($ipA, 1, 60));
        $this->assertFalse($limiter->isAllowed($ipA, 1, 60));
        $this->assertTrue($limiter->isAllowed($ipB, 1, 60));
    }

    public function testGetClientIpPrefersForwardedForFirstEntry(): void
    {
        $original = $_SERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 10.0.0.1';
        unset($_SERVER['HTTP_CLIENT_IP']);

        $ip = RateLimiter::getInstance()->getClientIp();

        $_SERVER = $original;

        $this->assertSame('203.0.113.5', $ip);
    }
}
