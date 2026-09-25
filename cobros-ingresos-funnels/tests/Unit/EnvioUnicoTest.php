<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EnvioUnico;
use PHPUnit\Framework\TestCase;

final class EnvioUnicoTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
    }

    /** El token de 64 hex que lleva un campo(). */
    private static function tokenDe(string $campo): string
    {
        if (preg_match('/name="' . EnvioUnico::CAMPO . '" value="([0-9a-f]{64})"/', $campo, $coincidencia) !== 1) {
            self::fail("El campo no lleva un token de 64 hex: {$campo}");
        }

        return $coincidencia[1];
    }

    public function testCadaFormularioRecibeUnTokenDistinto(): void
    {
        self::assertNotSame(self::tokenDe(EnvioUnico::campo()), self::tokenDe(EnvioUnico::campo()));
    }

    public function testElTokenQueGeneraElCampoSeReconoceAlRecibirlo(): void
    {
        $token = self::tokenDe(EnvioUnico::campo());
        $_POST[EnvioUnico::CAMPO] = $token;

        self::assertSame($token, EnvioUnico::tokenRecibido());
    }

    public function testUnPostSinTokenOConOtroFormatoNoTieneToken(): void
    {
        self::assertNull(EnvioUnico::tokenRecibido(), 'sin el campo');

        $_POST[EnvioUnico::CAMPO] = 'abc';
        self::assertNull(EnvioUnico::tokenRecibido(), 'demasiado corto');

        $_POST[EnvioUnico::CAMPO] = str_repeat('Z', 64);
        self::assertNull(EnvioUnico::tokenRecibido(), 'no es hexadecimal');
    }
}
