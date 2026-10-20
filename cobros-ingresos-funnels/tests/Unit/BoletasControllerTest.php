<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controllers\BoletasController;
use App\Validacion;
use PHPUnit\Framework\TestCase;

final class BoletasControllerTest extends TestCase
{
    public function testMontoMayorOIgualALoCobradoLoCubre(): void
    {
        $boleta = ['pagado' => 100.0];

        self::assertTrue(BoletasController::montoCubreLoYaCobrado(100.0, $boleta));
        self::assertTrue(BoletasController::montoCubreLoYaCobrado(150.0, $boleta));
    }

    /**
     * Reproduce el bug real: una boleta con $602.85 ya cobrados se podia
     * editar bajando su monto a $10, dejando un saldo negativo sin aviso.
     */
    public function testMontoMenorALoYaCobradoNoLoCubre(): void
    {
        $boleta = ['pagado' => 602.85];

        self::assertFalse(BoletasController::montoCubreLoYaCobrado(10.0, $boleta));
    }

    public function testSinPagosCualquierMontoPositivoLoCubre(): void
    {
        $boleta = ['pagado' => 0.0];

        self::assertTrue(BoletasController::montoCubreLoYaCobrado(1.0, $boleta));
    }

    /**
     * Antes tenia un margen de un centavo: bajar el monto de una boleta a
     * 602.84 con 602.85 cobrados pasaba, y la boleta quedaba "pagada" con un
     * centavo de sobrecobro sin aviso (justo lo que esta regla evita).
     */
    public function testUnCentavoPorDebajoDeLoYaCobradoNoLoCubre(): void
    {
        $boleta = ['pagado' => 602.85];

        self::assertFalse(BoletasController::montoCubreLoYaCobrado(602.84, $boleta));
        self::assertTrue(BoletasController::montoCubreLoYaCobrado(602.85, $boleta));
    }

    public function testLoCobradoLlegaComoTextoDeLaBase(): void
    {
        // BoletaRepository::porId() devuelve 'pagado' tal cual lo manda Postgres: un string.
        $boleta = ['pagado' => '602.85'];

        self::assertFalse(BoletasController::montoCubreLoYaCobrado(602.84, $boleta));
        self::assertTrue(BoletasController::montoCubreLoYaCobrado(602.85, $boleta));
    }

    /** Lo cobrado es la suma de varios pagos: el ruido de esa suma no tiene que rechazar un monto igual. */
    public function testElRuidoDeLaSumaDeLosPagosNoRechazaUnMontoIgual(): void
    {
        $boleta = ['pagado' => 0.1 + 0.2]; // 0.30000000000000004

        self::assertTrue(BoletasController::montoCubreLoYaCobrado(0.3, $boleta));
        self::assertFalse(BoletasController::montoCubreLoYaCobrado(0.29, $boleta));
    }

    public function testVencimientoPosteriorOIgualALaEmisionEsValido(): void
    {
        self::assertTrue(BoletasController::vencimientoNoAnteriorALaEmision('2026-01-15', '2026-02-15'));
        self::assertTrue(BoletasController::vencimientoNoAnteriorALaEmision('2026-01-15', '2026-01-15'));
    }

    /** Reproduce el caso real: emision 2026-06-01 con vencimiento 2020-01-01, aceptado antes del fix. */
    public function testVencimientoAnteriorALaEmisionNoEsValido(): void
    {
        self::assertFalse(BoletasController::vencimientoNoAnteriorALaEmision('2026-06-01', '2020-01-01'));
    }

    public function testSinPagosLaEmisionPuedeMoverseALibertad(): void
    {
        $boleta = ['primer_pago' => null];

        self::assertTrue(BoletasController::emisionNoPosteriorAlPrimerPago('2030-01-01', $boleta));
    }

    /** Reproduce el caso real: boleta con un pago del 2025-09-04, emision movida a 2026-01-01. */
    public function testLaEmisionNoPuedeQuedarDespuesDelPrimerPago(): void
    {
        $boleta = ['primer_pago' => '2025-09-04'];

        self::assertFalse(BoletasController::emisionNoPosteriorAlPrimerPago('2026-01-01', $boleta));
        self::assertTrue(BoletasController::emisionNoPosteriorAlPrimerPago('2025-09-04', $boleta));
        self::assertTrue(BoletasController::emisionNoPosteriorAlPrimerPago('2025-09-01', $boleta));
    }

    public function testLosCamposDeUnaBoletaBienNoDanError(): void
    {
        self::assertNull(BoletasController::errorDeLosCampos('Cuota', 100.0, '2026-01-01', '2026-02-01'));
        self::assertNull(BoletasController::errorDeLosCampos('Cuota', 100.0, '2026-01-01', '2026-01-01'), 'vencer el mismo dia de la emision esta bien');
    }

    public function testCadaCampoMalDaSuMensaje(): void
    {
        $faltan = 'Completá todos los campos con un monto válido.';
        self::assertSame($faltan, BoletasController::errorDeLosCampos('', 100.0, '2026-01-01', '2026-02-01'), 'sin concepto');
        self::assertSame($faltan, BoletasController::errorDeLosCampos('Cuota', 100.0, '', '2026-02-01'), 'sin emision');
        self::assertSame($faltan, BoletasController::errorDeLosCampos('Cuota', 0.0, '2026-01-01', '2026-02-01'), 'monto en cero');
        self::assertSame($faltan, BoletasController::errorDeLosCampos('Cuota', Validacion::MONTO_MAXIMO + 1, '2026-01-01', '2026-02-01'), 'monto que no cabe en la columna');
        self::assertSame(
            'El concepto no puede superar los 200 caracteres.',
            BoletasController::errorDeLosCampos(str_repeat('x', 201), 100.0, '2026-01-01', '2026-02-01')
        );
        self::assertSame(
            'La fecha de emisión o de vencimiento no es válida.',
            BoletasController::errorDeLosCampos('Cuota', 100.0, '2026-02-30', '2026-03-01')
        );
        self::assertSame(
            'El vencimiento no puede ser anterior a la emisión.',
            BoletasController::errorDeLosCampos('Cuota', 100.0, '2026-02-01', '2026-01-31')
        );
    }

    /** El orden es el del formulario de siempre: campos, largo, fechas reales, y recien ahi el orden entre fechas. */
    public function testConVariosErroresGanaElPrimeroDelOrden(): void
    {
        self::assertSame(
            'Completá todos los campos con un monto válido.',
            BoletasController::errorDeLosCampos('', 100.0, 'no-es-fecha', '2026-02-01')
        );
        self::assertSame(
            'El concepto no puede superar los 200 caracteres.',
            BoletasController::errorDeLosCampos(str_repeat('x', 201), 100.0, 'no-es-fecha', '2026-02-01')
        );
        self::assertSame(
            'La fecha de emisión o de vencimiento no es válida.',
            BoletasController::errorDeLosCampos('Cuota', 100.0, '2026-02-30', '2026-01-01'),
            'una fecha imposible gana sobre un vencimiento anterior'
        );
    }
}
