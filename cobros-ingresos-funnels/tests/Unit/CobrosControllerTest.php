<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controllers\CobrosController;
use PHPUnit\Framework\TestCase;

final class CobrosControllerTest extends TestCase
{
    public function testMontoMayorOIgualALoCobradoLoCubre(): void
    {
        $boleta = ['pagado' => 100.0];

        self::assertTrue(CobrosController::montoCubreLoYaCobrado(100.0, $boleta));
        self::assertTrue(CobrosController::montoCubreLoYaCobrado(150.0, $boleta));
    }

    /**
     * Reproduce el bug real: una boleta con $602.85 ya cobrados se podia
     * editar bajando su monto a $10, dejando un saldo negativo sin aviso.
     */
    public function testMontoMenorALoYaCobradoNoLoCubre(): void
    {
        $boleta = ['pagado' => 602.85];

        self::assertFalse(CobrosController::montoCubreLoYaCobrado(10.0, $boleta));
    }

    public function testSinPagosCualquierMontoPositivoLoCubre(): void
    {
        $boleta = ['pagado' => 0.0];

        self::assertTrue(CobrosController::montoCubreLoYaCobrado(1.0, $boleta));
    }
}
