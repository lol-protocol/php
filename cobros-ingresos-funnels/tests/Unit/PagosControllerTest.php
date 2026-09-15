<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controllers\PagosController;
use PHPUnit\Framework\TestCase;

final class PagosControllerTest extends TestCase
{
    public function testBoletaValidaDelMismoClienteYSinAnular(): void
    {
        $boleta = ['cliente_id' => 5, 'anulada' => false];

        self::assertTrue(PagosController::boletaEsValidaParaCliente($boleta, 5));
    }

    public function testBoletaInexistenteNoEsValida(): void
    {
        self::assertFalse(PagosController::boletaEsValidaParaCliente(null, 5));
    }

    public function testBoletaDeOtroClienteNoEsValida(): void
    {
        $boleta = ['cliente_id' => 5, 'anulada' => false];

        self::assertFalse(PagosController::boletaEsValidaParaCliente($boleta, 9));
    }

    public function testBoletaAnuladaNoEsValidaAunSiendoDelMismoCliente(): void
    {
        $boleta = ['cliente_id' => 5, 'anulada' => true];

        self::assertFalse(PagosController::boletaEsValidaParaCliente($boleta, 5));
    }

    public function testFechaPagoIgualOPosteriorALaEmisionEsValida(): void
    {
        $boleta = ['fecha_emision' => '2026-01-15'];

        self::assertTrue(PagosController::fechaPagoEsValida('2026-01-15', $boleta));
        self::assertTrue(PagosController::fechaPagoEsValida('2026-02-01', $boleta));
    }

    public function testFechaPagoAnteriorALaEmisionNoEsValida(): void
    {
        $boleta = ['fecha_emision' => '2026-01-15'];

        self::assertFalse(PagosController::fechaPagoEsValida('2026-01-14', $boleta));
    }

    public function testMontoMenorAlSaldoNoLoSupera(): void
    {
        $boleta = ['saldo' => 100.0];

        self::assertTrue(PagosController::montoNoSuperaElSaldo(50.0, $boleta));
    }

    public function testMontoExactoAlSaldoNoLoSupera(): void
    {
        $boleta = ['saldo' => 100.0];

        self::assertTrue(PagosController::montoNoSuperaElSaldo(100.0, $boleta), 'pagar exactamente el saldo restante debe ser valido');
    }

    public function testMontoMayorAlSaldoLoSupera(): void
    {
        $boleta = ['saldo' => 100.0];

        self::assertFalse(PagosController::montoNoSuperaElSaldo(100.02, $boleta));
    }
}
