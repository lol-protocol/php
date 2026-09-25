<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\HttpSecurityHeaders;
use PHPUnit\Framework\TestCase;

class HttpSecurityHeadersTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    /** Regression: SAPIs deliver SERVER_PORT as a string, which `=== 443` never matched. */
    public function testPort443AsStringIsHttps(): void
    {
        $_SERVER['SERVER_PORT'] = '443';

        $this->assertTrue(HttpSecurityHeaders::isHttps());
    }

    public function testHttpsFlagIsHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '8443';

        $this->assertTrue(HttpSecurityHeaders::isHttps());
    }

    public function testHttpsOffOnPort80IsNotHttps(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['SERVER_PORT'] = '80';

        $this->assertFalse(HttpSecurityHeaders::isHttps());
    }

    public function testMissingServerPortIsNotHttpsAndDoesNotWarn(): void
    {
        $this->assertFalse(HttpSecurityHeaders::isHttps());
    }
}
