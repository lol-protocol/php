<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\CabecerasDeSeguridad;
use PHPUnit\Framework\TestCase;

final class CabecerasDeSeguridadTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    public function testIncluyeLosHeadersBasicosSiempre(): void
    {
        $headers = CabecerasDeSeguridad::listado();

        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertSame('DENY', $headers['X-Frame-Options']);
        self::assertArrayHasKey('Referrer-Policy', $headers);
        self::assertArrayHasKey('Content-Security-Policy', $headers);
    }

    public function testLaCspBloqueaScriptsYPermiteEstilosInline(): void
    {
        $csp = CabecerasDeSeguridad::listado()['Content-Security-Policy'];

        self::assertStringContainsString("script-src 'none'", $csp);
        self::assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function testNoIncluyeHstsSinHttps(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

        self::assertArrayNotHasKey('Strict-Transport-Security', CabecerasDeSeguridad::listado());
    }

    public function testIncluyeHstsConHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';

        self::assertArrayHasKey('Strict-Transport-Security', CabecerasDeSeguridad::listado());
    }
}
