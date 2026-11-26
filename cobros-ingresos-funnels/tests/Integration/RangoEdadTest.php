<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\RangoEdad;

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

    /** "18-24" no tenia cota inferior: un menor de edad se contaba como adulto joven. */
    public function testUnMenorDeEdadNoCaeEnElPrimerTramoDeAdultos(): void
    {
        self::assertSame('Menor de 18', self::tramo(17));
        self::assertSame('Menor de 18', self::tramo(0), 'nacio hoy');
        self::assertSame('Menor de 18', self::tramo(18, 1), 'cumple 18 manana: todavia tiene 17');
        self::assertSame('18-24', self::tramo(18), 'cumple 18 hoy');
    }

    /** Una fecha posterior a hoy no es una edad: antes caia en "18-24" porque el anio de age() da 0 o negativo. */
    public function testUnaFechaDeNacimientoFuturaSeSeparaComoInvalida(): void
    {
        self::assertSame('Fecha inválida', self::tramo(0, 1), 'manana');
        self::assertSame('Fecha inválida', self::tramo(-1), 'dentro de un anio');
        self::assertSame('Fecha inválida', self::tramo(-30));
    }

    /**
     * Desde la migracion 021 el tramo se calcula en PHP (deFecha) y se guarda; expresionSql() ya no se usa en consultas. Las dos tienen
     * que decir lo mismo de cada fecha: de 0 a 70 anios, a dos dias de cada lado de cada cumpleanios, y las fechas que no son edad.
     */
    public function testElTramoDePhpEsElDeLaExpresionSql(): void
    {
        $hoy = new \DateTimeImmutable('today');
        $sql = Database::connection()->prepare('SELECT ' . RangoEdad::expresionSql('t.n') . ' FROM (SELECT :n::date AS n) t');

        for ($anios = -2; $anios <= 70; $anios++) {
            for ($dias = -2; $dias <= 2; $dias++) {
                $nacimiento = $hoy->modify("-{$anios} years")->modify(sprintf('%+d days', $dias))->format('Y-m-d');
                $sql->execute([':n' => $nacimiento]);

                self::assertSame((string) $sql->fetchColumn(), RangoEdad::deFecha($nacimiento, $hoy), "nacido el {$nacimiento}");
            }
        }
    }

    public function testDeFechaSeparaLasFechasQueNoSonEdad(): void
    {
        $hoy = new \DateTimeImmutable('2026-06-15');

        self::assertSame(RangoEdad::FECHA_INVALIDA, RangoEdad::deFecha('2026-06-16', $hoy), 'manana');
        self::assertSame(RangoEdad::FECHA_INVALIDA, RangoEdad::deFecha('2026-02-30', $hoy), 'un dia que no existe');
        self::assertSame(RangoEdad::FECHA_INVALIDA, RangoEdad::deFecha('no es una fecha', $hoy));
        self::assertSame(RangoEdad::MENOR, RangoEdad::deFecha('2026-06-15', $hoy), 'nacio hoy');
        self::assertSame('18-24', RangoEdad::deFecha('2008-06-15', $hoy), 'cumple 18 hoy');
        self::assertSame('65+', RangoEdad::deFecha('1961-06-15', $hoy), 'cumple 65 hoy');
        self::assertSame(['18-24', '25-34', '35-44', '45-54', '55-64', '65+'], RangoEdad::ADULTOS);
    }
}
