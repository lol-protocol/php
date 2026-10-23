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

    public function testRetryAfterTellsWhenTheOldestRequestLeavesTheWindow(): void
    {
        $limiter = RateLimiter::getInstance();
        $ip = $this->uniqueIp();

        $this->assertTrue($limiter->isAllowed($ip, 2, 60));
        $this->assertTrue($limiter->isAllowed($ip, 2, 60));
        $this->assertFalse($limiter->isAllowed($ip, 2, 60));

        // Both requests were made just now, so the wait is the whole window (give or take the second that ticked).
        $this->assertGreaterThanOrEqual(59, $limiter->retryAfter());
        $this->assertLessThanOrEqual(60, $limiter->retryAfter());
    }

    public function testRetryAfterShrinksAsTheOldestRequestAges(): void
    {
        $limiter = RateLimiter::getInstance();
        $ip = $this->uniqueIp();
        $file = sys_get_temp_dir() . '/rate_limit_' . hash('sha256', $ip);
        // A client that asked 50 seconds ago (twice) and is over a limit of 2 per minute.
        file_put_contents($file, json_encode([time() - 50, time() - 50]));

        $this->assertFalse($limiter->isAllowed($ip, 2, 60));

        $this->assertGreaterThanOrEqual(9, $limiter->retryAfter());
        $this->assertLessThanOrEqual(10, $limiter->retryAfter());
    }

    public function testRetryAfterIsAtLeastOneSecond(): void
    {
        $limiter = RateLimiter::getInstance();
        $ip = $this->uniqueIp();
        $file = sys_get_temp_dir() . '/rate_limit_' . hash('sha256', $ip);
        // The oldest request falls out of the window exactly now: still over the limit by this read, so wait a second.
        file_put_contents($file, json_encode([time() - 59, time() - 59]));

        $this->assertFalse($limiter->isAllowed($ip, 2, 60));

        $this->assertGreaterThanOrEqual(1, $limiter->retryAfter());
    }

    /** Files of clients that stopped coming are deleted; recent ones and files that are not the limiter's stay. */
    public function testSweepDeletesOnlyStaleLimiterFiles(): void
    {
        $dir = sys_get_temp_dir();
        $stale = $dir . '/rate_limit_' . hash('sha256', 'stale-' . uniqid());
        $fresh = $dir . '/rate_limit_' . hash('sha256', 'fresh-' . uniqid());
        $foreign = $dir . '/rate_limit_notes-' . uniqid();
        $other = $dir . '/other_' . hash('sha256', 'other-' . uniqid());
        array_push($this->filesToClean, $stale, $fresh, $foreign, $other);
        foreach ([$stale, $fresh, $foreign, $other] as $file) {
            file_put_contents($file, '[]');
        }
        touch($stale, time() - 7200);
        touch($foreign, time() - 7200);
        touch($other, time() - 7200);

        $deleted = RateLimiter::getInstance()->sweep();

        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($fresh, 'someone who asked a moment ago keeps their window');
        $this->assertFileExists($foreign, 'a file that is not named like the limiter\'s is not its business');
        $this->assertFileExists($other);
    }

    public function testSweepHonoursTheAgeItIsGiven(): void
    {
        $file = sys_get_temp_dir() . '/rate_limit_' . hash('sha256', 'age-' . uniqid());
        $this->filesToClean[] = $file;
        file_put_contents($file, '[]');
        touch($file, time() - 120);

        RateLimiter::getInstance()->sweep(3600);
        $this->assertFileExists($file, 'two minutes is not stale for an hour');

        RateLimiter::getInstance()->sweep(60);
        $this->assertFileDoesNotExist($file);
    }
}
