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

    /**
     * Reproduce el bug real: editar un pago de $484.10 a $5000 contra una
     * boleta con saldo (ya descontando este mismo pago) de $789.86 debia
     * rechazarse -el saldo disponible real es 789.86 + 484.10 = 1273.96-
     * pero sin sumar de vuelta el monto viejo se aceptaba cualquier cosa.
     */
    public function testEditarUnPagoPorEncimaDelSaldoDisponibleLoSupera(): void
    {
        $boleta = ['saldo' => 789.86];

        self::assertFalse(PagosController::montoNoSuperaElSaldoAlEditar(5000.0, $boleta, 484.10));
    }

    public function testEditarUnPagoDentroDelSaldoDisponibleNoLoSupera(): void
    {
        $boleta = ['saldo' => 789.86];

        self::assertTrue(PagosController::montoNoSuperaElSaldoAlEditar(600.0, $boleta, 484.10));
    }

    public function testEditarUnPagoAlMismoMontoSiempreEsValido(): void
    {
        // Dejar un pago sin cambios nunca deberia rechazarse por saldo,
        // sea cual sea el saldo restante de la boleta.
        $boleta = ['saldo' => 0.0];

        self::assertTrue(PagosController::montoNoSuperaElSaldoAlEditar(484.10, $boleta, 484.10));
    }
}
