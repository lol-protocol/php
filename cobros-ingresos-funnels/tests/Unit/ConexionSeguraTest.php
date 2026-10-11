<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ConexionSegura;
use PHPUnit\Framework\TestCase;

final class ConexionSeguraTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    public function testNoEsSeguraSinNingunIndicador(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        self::assertFalse(ConexionSegura::esHttps());
    }

    public function testNoEsSeguraConHttpsEnOff(): void
    {
        // Apache pone HTTPS=off en requests planos; no debe contar como seguro.
        $_SERVER['HTTPS'] = 'off';
        self::assertFalse(ConexionSegura::esHttps());
    }

    public function testEsSeguraConHttpsPresente(): void
    {
        $_SERVER['HTTPS'] = 'on';
        self::assertTrue(ConexionSegura::esHttps());
    }

    public function testEsSeguraDetrasDeUnProxyConXForwardedProto(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        self::assertTrue(ConexionSegura::esHttps());
    }

    public function testNoEsSeguraConXForwardedProtoHttp(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        self::assertFalse(ConexionSegura::esHttps());
    }
}
