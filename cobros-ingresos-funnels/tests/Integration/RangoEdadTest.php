<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\RangoEdad;

/** Corre contra la base configurada por las env vars DB_* (la expresion es SQL). */
final class RangoEdadTest extends IntegracionTestCase
{
    /** El tramo de alguien que cumple $anios dentro de $diasParaCumplir dias (0 = hoy). */
    private static function tramo(int $anios, int $diasParaCumplir = 0): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT ' . RangoEdad::expresionSql('nacimiento') . "
             FROM (SELECT (CURRENT_DATE - make_interval(years => :anios) + make_interval(days => :dias))::date AS nacimiento) t"
        );
        $stmt->execute([':anios' => $anios, ':dias' => $diasParaCumplir]);

        return (string) $stmt->fetchColumn();
    }

    /**
     * Reproduce el bug real: con dias / 365.25, quien cumple 25 hoy daba
     * 24.9993 y quedaba en 18-24. Con age() ya cumplio 25.
     */
    public function testElDiaDelCumpleaniosYaCuentaElAnioNuevo(): void
    {
        self::assertSame('25-34', self::tramo(25));
        self::assertSame('18-24', self::tramo(25, 1), 'un dia antes todavia tiene 24');
    }

    public function testCadaBordeCaeEnElTramoDeArriba(): void
    {
        self::assertSame('35-44', self::tramo(35));
        self::assertSame('45-54', self::tramo(45));
        self::assertSame('55-64', self::tramo(55));
        self::assertSame('65+', self::tramo(65));
        self::assertSame('55-64', self::tramo(65, 1));
    }
}
