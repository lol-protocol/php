<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http;
use PHPUnit\Framework\TestCase;

final class HttpTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    public function testNoEsSeguraSinNingunIndicador(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        self::assertFalse(Http::esSegura());
    }

    public function testNoEsSeguraConHttpsEnOff(): void
    {
        // Apache pone HTTPS=off en requests planos; no debe contar como seguro.
        $_SERVER['HTTPS'] = 'off';
        self::assertFalse(Http::esSegura());
    }

    public function testEsSeguraConHttpsPresente(): void
    {
        $_SERVER['HTTPS'] = 'on';
        self::assertTrue(Http::esSegura());
    }

    public function testEsSeguraDetrasDeUnProxyConXForwardedProto(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        self::assertTrue(Http::esSegura());
    }

    public function testNoEsSeguraConXForwardedProtoHttp(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        self::assertFalse(Http::esSegura());
    }
}
