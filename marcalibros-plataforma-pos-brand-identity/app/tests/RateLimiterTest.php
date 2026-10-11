<?php

namespace MarcalibrosApp\Tests;

use MarcalibrosApp\RateLimiter;

final class RateLimiterTest extends AppTestCase
{
    private function limiter(): RateLimiter
    {
        return new RateLimiter($this->dir . '/intentos');
    }

    public function testAllowsUpToTheLimitAndThenRefuses(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($limiter->attempt('crear:1.2.3.4', 3, 3600, $this->now + $i));
        }
        $this->assertFalse($limiter->attempt('crear:1.2.3.4', 3, 3600, $this->now + 5));
    }

    public function testEachSubjectHasItsOwnCount(): void
    {
        $limiter = $this->limiter();
        $this->assertTrue($limiter->attempt('crear:1.1.1.1', 1, 3600, $this->now));
        $this->assertFalse($limiter->attempt('crear:1.1.1.1', 1, 3600, $this->now));
        $this->assertTrue($limiter->attempt('crear:2.2.2.2', 1, 3600, $this->now));
    }

    public function testAttemptsExpireAfterTheWindow(): void
    {
        $limiter = $this->limiter();
        $this->assertTrue($limiter->attempt('s', 1, 3600, $this->now));
        $this->assertFalse($limiter->attempt('s', 1, 3600, $this->now + 3599));
        $this->assertTrue($limiter->attempt('s', 1, 3600, $this->now + 3601));
    }

    public function testRefusedAttemptsAreNotCounted(): void
    {
        $limiter = $this->limiter();
        $limiter->attempt('s', 1, 100, $this->now);
        for ($i = 1; $i <= 20; $i++) {
            $limiter->attempt('s', 1, 100, $this->now + $i);
        }
        $this->assertTrue($limiter->attempt('s', 1, 100, $this->now + 101), 'Insistir no debe alargar el bloqueo.');
    }

    public function testRetryAfterIsTheTimeUntilTheOldestAttemptExpires(): void
    {
        $limiter = $this->limiter();
        $limiter->attempt('s', 2, 3600, $this->now);
        $limiter->attempt('s', 2, 3600, $this->now + 600);
        $this->assertSame(3600 - 900, $limiter->retryAfter('s', 3600, $this->now + 900));
        $this->assertSame(1, $limiter->retryAfter('nunca-visto', 3600, $this->now));
    }

    public function testTheStoredFilesDoNotRevealTheAddress(): void
    {
        $this->limiter()->attempt('crear:203.0.113.77', 5, 3600, $this->now);
        $names = array_diff(scandir($this->dir . '/intentos') ?: [], ['.', '..']);
        $this->assertGreaterThan(0, count($names));
        foreach ($names as $name) {
            $this->assertStringNotContainsString('203.0.113.77', $name);
            if ($name !== '.clave') {
                $this->assertStringNotContainsString('203.0.113.77', (string) file_get_contents($this->dir . "/intentos/$name"));
            }
        }
    }

    public function testTheSecretIsCreatedOnceAndReused(): void
    {
        $this->limiter()->attempt('s', 5, 3600, $this->now);
        $secret = (string) file_get_contents($this->dir . '/intentos/.clave');
        $this->assertGreaterThanOrEqual(32, strlen($secret));
        $this->assertSame('600', substr(sprintf('%o', fileperms($this->dir . '/intentos/.clave')), -3));

        $second = $this->limiter();
        $this->assertTrue($second->attempt('s', 5, 3600, $this->now));
        $this->assertSame($secret, (string) file_get_contents($this->dir . '/intentos/.clave'));
        $this->assertCount(1, glob($this->dir . '/intentos/*.json') ?: [], 'La misma persona debe caer en el mismo registro.');
    }

    public function testPurgeRemovesOnlyStaleRecords(): void
    {
        $limiter = $this->limiter();
        $limiter->attempt('viejo', 5, 3600, $this->now);
        $limiter->attempt('nuevo', 5, 3600, $this->now);
        $files = glob($this->dir . '/intentos/*.json') ?: [];
        $this->assertCount(2, $files);
        touch($files[0], $this->now - 7200);
        touch($files[1], $this->now);

        $this->assertSame(1, $limiter->purge(3600, $this->now));
        $this->assertCount(1, glob($this->dir . '/intentos/*.json') ?: []);
        $this->assertFileExists($this->dir . '/intentos/.clave');
    }
}
