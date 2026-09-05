<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repositories\IntentoLoginRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. Usa un email
 * dedicado y lo limpia antes/despues de cada test para no interferir con
 * otros datos de la tabla intentos_login.
 */
final class IntentoLoginRepositoryTest extends TestCase
{
    private const EMAIL_PRUEBA = 'test-fuerza-bruta@example.com';

    protected function setUp(): void
    {
        (new IntentoLoginRepository())->limpiar(self::EMAIL_PRUEBA);
    }

    protected function tearDown(): void
    {
        (new IntentoLoginRepository())->limpiar(self::EMAIL_PRUEBA);
    }

    public function testSinIntentosNoEstaBloqueado(): void
    {
        $repo = new IntentoLoginRepository();
        self::assertNull($repo->minutosDeBloqueo(self::EMAIL_PRUEBA));
    }

    public function testMenosDeCincoIntentosSeguidosNoBloquea(): void
    {
        $repo = new IntentoLoginRepository();

        for ($i = 0; $i < 4; $i++) {
            $repo->registrarFallo(self::EMAIL_PRUEBA);
        }

        self::assertNull($repo->minutosDeBloqueo(self::EMAIL_PRUEBA));
    }

    public function testQuintoIntentoSeguidoBloqueaPorUnRatoPositivo(): void
    {
        $repo = new IntentoLoginRepository();

        for ($i = 0; $i < 5; $i++) {
            $repo->registrarFallo(self::EMAIL_PRUEBA);
        }

        $minutos = $repo->minutosDeBloqueo(self::EMAIL_PRUEBA);
        self::assertNotNull($minutos, 'al quinto intento fallido seguido deberia quedar bloqueado');
        self::assertGreaterThan(0, $minutos);
        self::assertLessThanOrEqual(15, $minutos);
    }

    public function testLimpiarQuitaElBloqueoYReiniciaElContador(): void
    {
        $repo = new IntentoLoginRepository();
        for ($i = 0; $i < 5; $i++) {
            $repo->registrarFallo(self::EMAIL_PRUEBA);
        }
        self::assertNotNull($repo->minutosDeBloqueo(self::EMAIL_PRUEBA));

        $repo->limpiar(self::EMAIL_PRUEBA);

        self::assertNull($repo->minutosDeBloqueo(self::EMAIL_PRUEBA));
    }
}
