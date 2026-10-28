<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\FechaDeDevolucion;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FechaDeDevolucionTest extends TestCase
{
    private const NOTA = '2026-03-10';
    private const HOY = '2026-03-20';

    public function testDevuelveLaFechaCuandoEstaEntreLaNotaYHoy(): void
    {
        self::assertSame('2026-03-15', FechaDeDevolucion::validar('2026-03-15', self::NOTA, self::HOY));
    }

    public function testLosDosExtremosSonValidos(): void
    {
        self::assertSame(self::NOTA, FechaDeDevolucion::validar(self::NOTA, self::NOTA, self::HOY), 'el mismo dia de la nota');
        self::assertSame(self::HOY, FechaDeDevolucion::validar(self::HOY, self::NOTA, self::HOY), 'hoy');
    }

    public function testQuitaLosEspaciosDeLosBordes(): void
    {
        self::assertSame('2026-03-15', FechaDeDevolucion::validar("  2026-03-15\n", self::NOTA, self::HOY));
    }

    #[DataProvider('textosQueNoSonUnaFecha')]
    public function testRechazaLoQueNoEsUnaFechaReal(string $texto): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha de devolución no es válida.');

        FechaDeDevolucion::validar($texto, self::NOTA, self::HOY);
    }

    /** @return array<string, array{string}> */
    public static function textosQueNoSonUnaFecha(): array
    {
        return [
            'vacio' => [''],
            'solo espacios' => ['   '],
            'texto' => ['ayer'],
            'dia que no existe' => ['2026-02-30'],
            'mes que no existe' => ['2026-13-01'],
            'otro formato' => ['15/03/2026'],
        ];
    }

    public function testRechazaUnaFechaAnteriorALaNotaYDiceCualEra(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha de devolución no puede ser anterior a la nota de crédito (2026-03-10).');

        FechaDeDevolucion::validar('2026-03-09', self::NOTA, self::HOY);
    }

    public function testRechazaUnaFechaFutura(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha de devolución no puede ser futura.');

        FechaDeDevolucion::validar('2026-03-21', self::NOTA, self::HOY);
    }
}
