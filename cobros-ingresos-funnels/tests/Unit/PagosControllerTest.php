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

    /** Antes tenia un margen de un centavo: pasarse por 0.01 se cobraba y dejaba la boleta con saldo negativo. */
    public function testUnCentavoPorEncimaDelSaldoLoSupera(): void
    {
        $boleta = ['saldo' => 100.0];

        self::assertFalse(PagosController::montoNoSuperaElSaldo(100.01, $boleta));
        self::assertTrue(PagosController::montoNoSuperaElSaldo(100.00, $boleta));
    }

    public function testElSaldoLlegaComoTextoODeUnaSumaConRuido(): void
    {
        self::assertTrue(PagosController::montoNoSuperaElSaldo(0.3, ['saldo' => 0.1 + 0.2]), 'saldo con ruido: 0.30000000000000004');
        self::assertFalse(PagosController::montoNoSuperaElSaldo(0.31, ['saldo' => 0.1 + 0.2]));
        self::assertTrue(PagosController::montoNoSuperaElSaldo(602.85, ['saldo' => '602.85']));
        self::assertFalse(PagosController::montoNoSuperaElSaldo(602.86, ['saldo' => '602.85']));
    }

    /**
     * Pagar exactamente lo disponible al editar tiene que pasar aunque la suma
     * saldo + monto viejo, en float, de un valor apenas menor (1370575.8399999999
     * en vez de 1370575.84): sin centavos enteros, o sin el margen de un
     * centavo que se quita, el pago exacto se rechazaria por ruido.
     */
    public function testEditarUnPagoPorElTotalDisponibleSeAceptaAunqueLaSumaEnFloatQuedeCorta(): void
    {
        $boleta = ['saldo' => 807751.45];
        $montoViejo = 562824.39;

        self::assertLessThan(1370575.84, 807751.45 + 562824.39, 'el caso es real: el float queda corto');
        self::assertTrue(PagosController::montoNoSuperaElSaldoAlEditar(1370575.84, $boleta, $montoViejo));
    }

    public function testEditarUnPagoUnCentavoPorEncimaDelDisponibleLoSupera(): void
    {
        $boleta = ['saldo' => 807751.45];

        self::assertFalse(PagosController::montoNoSuperaElSaldoAlEditar(1370575.85, $boleta, 562824.39));
    }

    /**
     * Una boleta sobrecobrada por un centavo (saldo negativo; solo pudo pasar
     * con el margen de antes) sigue dejando editar sus pagos sin cambiar el
     * monto, por ejemplo para corregir la fecha.
     */
    public function testEditarUnPagoDeUnaBoletaSobrecobradaConElMismoMontoOMenosSeAcepta(): void
    {
        $boleta = ['saldo' => -0.01];

        self::assertTrue(PagosController::montoNoSuperaElSaldoAlEditar(484.10, $boleta, 484.10));
        self::assertTrue(PagosController::montoNoSuperaElSaldoAlEditar(484.09, $boleta, 484.10));
        self::assertFalse(PagosController::montoNoSuperaElSaldoAlEditar(484.11, $boleta, 484.10));
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

    /**
     * Reproduce el caso real: al anular una boleta con pagos se emite una
     * nota de credito por lo cobrado, y esa nota no se recalcula. Anular
     * despues el pago sacaba la plata de la caja dejando la devolucion en
     * pie, o sea descontandola dos veces del cobrado neto.
     */
    public function testUnPagoDeBoletaAnuladaQuedaCongelado(): void
    {
        self::assertTrue(PagosController::boletaAnuladaCongelaElPago(['anulada' => true]));
    }

    public function testUnPagoDeBoletaVigenteSePuedeTocar(): void
    {
        self::assertFalse(PagosController::boletaAnuladaCongelaElPago(['anulada' => false]));
    }

    /** Un anticipo (pago sin boleta) no tiene nota de credito que descuadrar. */
    public function testUnPagoSinBoletaNoQuedaCongelado(): void
    {
        self::assertFalse(PagosController::boletaAnuladaCongelaElPago(null));
    }

    public function testLosMetodosQueOfreceElFormularioSonValidos(): void
    {
        foreach (['transferencia', 'tarjeta', 'efectivo'] as $metodo) {
            self::assertTrue(PagosController::metodoEsValido($metodo), $metodo);
        }
    }

    /** Antes solo lo impedia el <select> del navegador: un POST a mano guardaba cualquier texto. */
    public function testUnMetodoFueraDeLaListaNoEsValido(): void
    {
        foreach (['bitcoin', '', 'Tarjeta', 'tarjeta ', 'transferencia;'] as $metodo) {
            self::assertFalse(PagosController::metodoEsValido($metodo), "'{$metodo}'");
        }
    }
}
