<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\MayoriaDeEdad;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La regla en PHP, con la fecha de "hoy" fija. Que coincida con el trigger de
 * la base (pais por pais) y con RangoEdad en el borde lo comprueba
 * ClientesMayoresDeEdadTest.
 */
final class MayoriaDeEdadTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> nacimiento, hoy, si ya es mayor de edad */
    public static function casos(): iterable
    {
        yield 'cumple 18 hoy' => ['2008-06-15', '2026-06-15', true];
        yield 'cumplio 18 ayer' => ['2008-06-14', '2026-06-15', true];
        yield 'cumple 18 manana: todavia tiene 17' => ['2008-06-16', '2026-06-15', false];
        yield 'tiene 17' => ['2009-06-15', '2026-06-15', false];
        yield 'nacio hoy' => ['2026-06-15', '2026-06-15', false];
        yield 'adulto mayor' => ['1950-01-01', '2026-06-15', true];

        // Quien nacio un 29 de febrero cumple en un anio comun el 1 de marzo.
        yield 'nacio un 29 de febrero: el 28 de febrero todavia no cumple' => ['2008-02-29', '2026-02-28', false];
        yield 'nacio un 29 de febrero: el 1 de marzo ya cumplio' => ['2008-02-29', '2026-03-01', true];

        // Hoy es 29 de febrero: restarle 18 anios a mano da el 1 de marzo de 2010.
        yield 'hoy es 29 de febrero: nacio el 28 de febrero de 2010' => ['2010-02-28', '2028-02-29', true];
        yield 'hoy es 29 de febrero: nacio el 1 de marzo de 2010 y tiene 17' => ['2010-03-01', '2028-02-29', false];

        yield 'fecha futura' => ['2027-01-01', '2026-06-15', false];
        yield 'dia que no existe' => ['2000-02-30', '2026-06-15', false];
        yield 'mes que no existe' => ['2000-13-01', '2026-06-15', false];
        yield 'no es una fecha' => ['no-es-una-fecha', '2026-06-15', false];
        yield 'vacia' => ['', '2026-06-15', false];
    }

    #[DataProvider('casos')]
    public function testCumplidaCuentaAniosCumplidos(string $nacimiento, string $hoy, bool $esperado): void
    {
        self::assertSame($esperado, MayoriaDeEdad::cumplida($nacimiento, new DateTimeImmutable($hoy)));
    }

    public function testLaHoraDelDiaNoCambiaElResultado(): void
    {
        self::assertTrue(MayoriaDeEdad::cumplida('2008-06-15', new DateTimeImmutable('2026-06-15 00:00:01')));
        self::assertTrue(MayoriaDeEdad::cumplida('2008-06-15', new DateTimeImmutable('2026-06-15 23:59:59')));
        self::assertFalse(MayoriaDeEdad::cumplida('2008-06-16', new DateTimeImmutable('2026-06-15 23:59:59')));
    }

    public function testElTopeDelFormularioEsLaFechaMasRecienteQueYaEsDeUnMayor(): void
    {
        self::assertSame('2008-06-15', MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2026-06-15')));
        self::assertSame('2008-03-01', MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2026-03-01')));
    }

    public function testElTopeDelFormularioUnDiaDeBisiestoNoDejaPasarAUnMenor(): void
    {
        self::assertSame('2010-02-28', MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2028-02-29')));
        self::assertSame('2006-02-28', MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2024-02-29')));
    }

    /** El tope es el ultimo dia que sirve: el siguiente ya es de un menor, en cualquier dia del anio. */
    public function testElTopeEsExactoTodoElAnio(): void
    {
        $dia = new DateTimeImmutable('2027-12-20');
        for ($i = 0; $i < 400; $i++, $dia = $dia->modify('+1 day')) {
            $tope = MayoriaDeEdad::nacimientoMasReciente($dia);
            $siguiente = (new DateTimeImmutable($tope))->modify('+1 day')->format('Y-m-d');

            self::assertTrue(MayoriaDeEdad::cumplida($tope, $dia), "el tope {$tope} tiene que ser de un mayor el {$dia->format('Y-m-d')}");
            self::assertFalse(MayoriaDeEdad::cumplida($siguiente, $dia), "{$siguiente} tiene que ser de un menor el {$dia->format('Y-m-d')}");
        }
    }

    /** @return iterable<string, array{string, string, int, bool}> nacimiento, hoy, edad de mayoria del pais, si ya es mayor */
    public static function casosPorEdad(): iterable
    {
        yield '20 anios en Tailandia: cumple 20 hoy' => ['2006-06-15', '2026-06-15', 20, true];
        yield '20 anios en Tailandia: cumple 20 manana' => ['2006-06-16', '2026-06-15', 20, false];
        yield 'tiene 19 y en Tailandia piden 20' => ['2007-06-15', '2026-06-15', 20, false];
        yield 'tiene 19 y en Argentina piden 18' => ['2007-06-15', '2026-06-15', 18, true];
        yield '21 en Singapur: cumple 21 hoy' => ['2005-06-15', '2026-06-15', 21, true];
        yield '21 en Singapur: tiene 20' => ['2005-06-16', '2026-06-15', 21, false];

        // Un 29 de febrero tambien: quien nacio el 29 de febrero de 2008 cumple 20 en 2028 (bisiesto) el mismo dia.
        yield '29 de febrero, 20 anios, bisiesto: el 28 todavia no' => ['2008-02-29', '2028-02-28', 20, false];
        yield '29 de febrero, 20 anios, bisiesto: el 29 ya cumplio' => ['2008-02-29', '2028-02-29', 20, true];
        yield '29 de febrero, 21 anios, comun: el 28 todavia no' => ['2008-02-29', '2029-02-28', 21, false];
        yield '29 de febrero, 21 anios, comun: el 1 de marzo ya cumplio' => ['2008-02-29', '2029-03-01', 21, true];
    }

    #[DataProvider('casosPorEdad')]
    public function testCumplidaUsaLaEdadDelPais(string $nacimiento, string $hoy, int $edad, bool $esperado): void
    {
        self::assertSame($esperado, MayoriaDeEdad::cumplida($nacimiento, new DateTimeImmutable($hoy), $edad));
    }

    public function testSinEdadSeUsaLaGeneral(): void
    {
        self::assertSame(18, MayoriaDeEdad::POR_DEFECTO);
        self::assertTrue(MayoriaDeEdad::cumplida('2008-06-15', new DateTimeImmutable('2026-06-15')));
        self::assertSame(
            MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2026-06-15'), 18),
            MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2026-06-15')),
        );
    }

    public function testElTopeDelFormularioSigueLaEdadQueSePide(): void
    {
        self::assertSame('2008-06-15', MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2026-06-15'), 18));
        self::assertSame('2007-06-15', MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2026-06-15'), 19));
        self::assertSame('2006-06-15', MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2026-06-15'), 20));
        self::assertSame('2005-06-15', MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('2026-06-15'), 21));
    }

    /** El tope es el ultimo dia que sirve, con cualquier edad y en cualquier dia del anio (los 29 de febrero incluidos). */
    public function testElTopeEsExactoTodoElAnioConCadaEdad(): void
    {
        foreach ([16, 18, 19, 20, 21, 25] as $edad) {
            $dia = new DateTimeImmutable('2027-12-20');
            for ($i = 0; $i < 400; $i++, $dia = $dia->modify('+1 day')) {
                $tope = MayoriaDeEdad::nacimientoMasReciente($dia, $edad);
                $siguiente = (new DateTimeImmutable($tope))->modify('+1 day')->format('Y-m-d');

                self::assertTrue(MayoriaDeEdad::cumplida($tope, $dia, $edad), "{$edad}: el tope {$tope} tiene que ser de un mayor el {$dia->format('Y-m-d')}");
                self::assertFalse(MayoriaDeEdad::cumplida($siguiente, $dia, $edad), "{$edad}: {$siguiente} tiene que ser de un menor el {$dia->format('Y-m-d')}");
            }
        }
    }

    public function testElMensajeDiceLaEdad(): void
    {
        self::assertSame('Solo se admiten clientes mayores de edad (18 años cumplidos).', MayoriaDeEdad::mensaje());
    }

    public function testElMensajeNombraAlPaisSoloCuandoSuEdadNoEsLaGeneral(): void
    {
        self::assertSame(
            'Solo se admiten clientes mayores de edad (en Tailandia, 20 años cumplidos).',
            MayoriaDeEdad::mensaje(20, 'Tailandia'),
        );
        self::assertSame(
            'Solo se admiten clientes mayores de edad (18 años cumplidos).',
            MayoriaDeEdad::mensaje(18, 'Argentina'),
            'con la edad general el pais sobra',
        );
        self::assertSame('Solo se admiten clientes mayores de edad (20 años cumplidos).', MayoriaDeEdad::mensaje(20), 'sin pais, solo la edad');
    }

    public function testElMensajeGeneralNoAfirmaUnaEdad(): void
    {
        self::assertStringContainsString('mayores de edad', MayoriaDeEdad::mensajeGeneral());
        self::assertDoesNotMatchRegularExpression('/\d/', MayoriaDeEdad::mensajeGeneral());
    }
}
