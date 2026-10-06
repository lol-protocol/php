<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EstadoBoleta;
use App\Repositories\IngresosRepository;
use PHPUnit\Framework\TestCase;

final class TramoAntiguedadTest extends TestCase
{
    /** Mismo criterio que EstadoBoleta: la que vence hoy no esta vencida. */
    public function testLaQueVenceHoyEstaAlDiaComoEnElListado(): void
    {
        self::assertSame('Al día', IngresosRepository::tramoDeAntiguedad(0));
        self::assertSame('pendiente', EstadoBoleta::calcular(100.0, 0.0, '2026-09-25', '2026-09-25')['estado']);
    }

    public function testTramos(): void
    {
        self::assertSame('Al día', IngresosRepository::tramoDeAntiguedad(-10));
        self::assertSame('1-30 días', IngresosRepository::tramoDeAntiguedad(1));
        self::assertSame('1-30 días', IngresosRepository::tramoDeAntiguedad(30));
        self::assertSame('31-60 días', IngresosRepository::tramoDeAntiguedad(31));
        self::assertSame('31-60 días', IngresosRepository::tramoDeAntiguedad(60));
        self::assertSame('61+ días', IngresosRepository::tramoDeAntiguedad(61));
    }
}
