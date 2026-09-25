<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SESSION['csrf_token'], $_POST['csrf_token']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['csrf_token'], $_POST['csrf_token']);
    }

    public function testTokenSeGeneraUnaVezYSeMantieneEstable(): void
    {
        $primero = Csrf::token();
        $segundo = Csrf::token();

        self::assertNotSame('', $primero);
        self::assertSame($primero, $segundo, 'debe ser el mismo token dentro de la misma sesion');
    }

    public function testTokenTieneLongitudSuficiente(): void
    {
        // 32 bytes aleatorios en hexadecimal = 64 caracteres.
        self::assertSame(64, strlen(Csrf::token()));
    }

    public function testCampoIncluyeElTokenActualComoInputOculto(): void
    {
        $token = Csrf::token();
        $html = Csrf::campo();

        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('name="csrf_token"', $html);
        self::assertStringContainsString('value="' . $token . '"', $html);
    }

    public function testValidoEsFalsoSinTokenEnviado(): void
    {
        Csrf::token();
        unset($_POST['csrf_token']);

        self::assertFalse(Csrf::valido());
    }

    public function testValidoEsFalsoConTokenIncorrecto(): void
    {
        Csrf::token();
        $_POST['csrf_token'] = 'un-token-cualquiera-invalido';

        self::assertFalse(Csrf::valido());
    }

    public function testValidoEsVerdaderoConElTokenCorrecto(): void
    {
        $_POST['csrf_token'] = Csrf::token();

        self::assertTrue(Csrf::valido());
    }
}
