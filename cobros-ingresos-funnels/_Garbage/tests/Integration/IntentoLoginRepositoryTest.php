<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\IntentoLoginRepository;

/**
 * Corre contra la base configurada por las env vars DB_*. Usa un email
 * dedicado y lo limpia al empezar, por si quedo algun intento de antes; lo
 * que cada test agrega se deshace con su transaccion.
 */
final class IntentoLoginRepositoryTest extends IntegracionTestCase
{
    private const EMAIL_PRUEBA = 'test-fuerza-bruta@example.com';

    protected function setUp(): void
    {
        parent::setUp();
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

    /**
     * Reproduce el bug real: un bloqueo que ya vencio hace dias, y un unico
     * intento fallido nuevo. Sin el reset por ventana, esto volvia a
     * bloquear de una (5+1 >= 5); con el fix debe arrancar de 1 y no bloquear.
     */
    public function testUnIntentoMuchoDespuesDeUnBloqueoVencidoNoRebloqueaDeUna(): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO intentos_login (email, intentos, ultimo_intento, bloqueado_hasta)
             VALUES (:email, 5, now() - interval '3 days', now() - interval '3 days')"
        );
        $stmt->execute([':email' => self::EMAIL_PRUEBA]);

        $repo = new IntentoLoginRepository();
        $repo->registrarFallo(self::EMAIL_PRUEBA);

        self::assertNull($repo->minutosDeBloqueo(self::EMAIL_PRUEBA), 'una racha vieja y ya vencida no deberia rebloquear con un solo intento nuevo');
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
