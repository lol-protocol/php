<?php

namespace MarcalibrosApp\Tests;

use MarcalibrosApp\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaultsWhenNothingIsSet(): void
    {
        $config = Config::fromEnvironment('/srv/app', []);
        $this->assertSame('/srv', $config->projectRoot);
        $this->assertSame('/srv/app/storage', $config->storageDirectory);
        $this->assertSame(0, $config->retentionDays, 'Por defecto no se borra nada.');
        $this->assertSame(10, $config->createsPerHour);
        $this->assertSame(5000, $config->maxStored);
        $this->assertFalse($config->trustForwardedFor);
    }

    public function testReadsEnvironmentValues(): void
    {
        $config = Config::fromEnvironment('/srv/app', [
            'MARCALIBROS_STORAGE' => '/var/lib/marcalibros/',
            'MARCALIBROS_PROJECT_ROOT' => '/opt/marcalibros',
            'MARCALIBROS_RETENTION_DAYS' => '7',
            'MARCALIBROS_CREATES_PER_HOUR' => '3',
            'MARCALIBROS_MAX_STORED' => '100',
            'MARCALIBROS_TRUST_FORWARDED_FOR' => '1',
        ]);
        $this->assertSame('/var/lib/marcalibros', $config->storageDirectory);
        $this->assertSame('/opt/marcalibros', $config->projectRoot);
        $this->assertSame(7, $config->retentionDays);
        $this->assertSame(3, $config->createsPerHour);
        $this->assertSame(100, $config->maxStored);
        $this->assertTrue($config->trustForwardedFor);
    }

    public function testEmptyValuesFallBackToDefaults(): void
    {
        $config = Config::fromEnvironment('/srv/app', ['MARCALIBROS_RETENTION_DAYS' => '', 'MARCALIBROS_STORAGE' => '']);
        $this->assertSame(0, $config->retentionDays);
        $this->assertSame('/srv/app/storage', $config->storageDirectory);
    }

    public function testNonNumericValueIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Config::fromEnvironment('/srv/app', ['MARCALIBROS_RETENTION_DAYS' => 'treinta']);
    }

    public function testZeroOrNegativeLimitsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Config::fromEnvironment('/srv/app', ['MARCALIBROS_CREATES_PER_HOUR' => '0']);
    }

    public function testRetentionZeroMeansNeverButNegativeIsRejected(): void
    {
        $this->assertSame(0, Config::fromEnvironment('/srv/app', ['MARCALIBROS_RETENTION_DAYS' => '0'])->retentionDays);
        $this->expectException(\InvalidArgumentException::class);
        Config::fromEnvironment('/srv/app', ['MARCALIBROS_RETENTION_DAYS' => '-1']);
    }

    public function testForwardedForIsOnlyTrustedWithExactlyOne(): void
    {
        $this->assertFalse(Config::fromEnvironment('/srv/app', ['MARCALIBROS_TRUST_FORWARDED_FOR' => 'true'])->trustForwardedFor);
    }

    public function testPublicUrlIsOptionalAndValidated(): void
    {
        $this->assertNull(Config::fromEnvironment('/srv/app', [])->publicUrl);
        $this->assertSame('https://marcalibros.example.com', Config::fromEnvironment('/srv/app', ['MARCALIBROS_PUBLIC_URL' => 'https://marcalibros.example.com/'])->publicUrl);
        foreach (['marcalibros.example.com', 'https://example.com/ruta', 'javascript:alert(1)', 'https://example.com?x=1'] as $bad) {
            try {
                Config::fromEnvironment('/srv/app', ['MARCALIBROS_PUBLIC_URL' => $bad]);
                $this->fail("Se esperaba un error con $bad");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
