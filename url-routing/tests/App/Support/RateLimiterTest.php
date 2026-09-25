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

    private function clientIpWith(array $server, string $trustedProxies = ''): string
    {
        $original = $_SERVER;
        putenv('TRUSTED_PROXIES=' . $trustedProxies);
        $_SERVER = $server;

        try {
            return RateLimiter::getInstance()->getClientIp();
        } finally {
            $_SERVER = $original;
            putenv('TRUSTED_PROXIES');
        }
    }

    public function testForwardedHeadersAreIgnoredWithoutTrustedProxy(): void
    {
        $ip = $this->clientIpWith([
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.5',
            'HTTP_CLIENT_IP' => '203.0.113.9',
        ]);

        $this->assertSame('198.51.100.7', $ip);
    }

    public function testForwardedForIsUsedWhenPeerIsTrustedProxy(): void
    {
        $ip = $this->clientIpWith(
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.5'],
            '10.0.0.1'
        );

        $this->assertSame('203.0.113.5', $ip);
    }

    public function testSpoofedLeftmostForwardedEntryIsNotTrusted(): void
    {
        // Client sent "X-Forwarded-For: 1.2.3.4"; our proxy appended the real peer.
        $ip = $this->clientIpWith(
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.5'],
            '10.0.0.1'
        );

        $this->assertSame('203.0.113.5', $ip);
    }

    public function testChainOfTrustedProxiesIsSkipped(): void
    {
        $ip = $this->clientIpWith(
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.5, 10.0.0.2'],
            '10.0.0.1,10.0.0.2'
        );

        $this->assertSame('203.0.113.5', $ip);
    }
}
