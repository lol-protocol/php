<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controllers\BoletasController;
use App\Controllers\PagosController;
use App\Repositories\MonedaRepository;

/**
 * Los decimales de cada moneda (monedas.decimales, migracion 014): el yen, el won y el peso chileno no
 * tienen fraccion; las monedas de 3 decimales (dinar kuwaiti) se guardan con 2 porque las columnas de
 * monto son NUMERIC(14, 2).
 */
final class MontosSinDecimalesTest extends IntegracionTestCase
{
    public function testLosDecimalesSalenDeIso4217(): void
    {
        self::assertSame(0, MonedaRepository::decimales('JPY'));
        self::assertSame(0, MonedaRepository::decimales('KRW'));
        self::assertSame(0, MonedaRepository::decimales('CLP'));
        self::assertSame(0, MonedaRepository::decimales('XOF'));
        self::assertSame(2, MonedaRepository::decimales('USD'));
        self::assertSame(2, MonedaRepository::decimales('EUR'));
    }

    /** ISO dice 3 para el dinar kuwaiti, pero el sistema guarda 2: el dato ISO se conserva y el tope se aplica al usarlo. */
    public function testUnaMonedaDeTresDecimalesSeGuardaYSeMuestraConDos(): void
    {
        self::assertSame(3, MonedaRepository::decimalesPorMoneda()['KWD']);
        self::assertSame(2, MonedaRepository::decimales('KWD'));
        self::assertSame(MonedaRepository::simbolo('KWD') . '1,234.50 KWD', dinero_en_moneda(1234.5, 'KWD'));
    }

    public function testUnaMonedaFueraDelCatalogoTieneDosDecimales(): void
    {
        self::assertSame(2, MonedaRepository::decimales('ZZZ'));
    }

    public function testUnaMonedaSinDecimalesSeMuestraEntera(): void
    {
        self::assertSame('¥1,234 JPY', dinero_en_moneda(1234.0, 'JPY'));
        self::assertSame('-¥1,234 JPY', dinero_en_moneda(-1234.0, 'JPY'));
        self::assertSame('¥0 JPY', dinero_en_moneda(0.0, 'JPY'));
        self::assertSame('$1,234.00 USD', dinero_en_moneda(1234.0, 'USD'), 'las demas siguen con sus dos decimales');
    }

    /** Un monto cargado antes de que se pidieran enteros no se redondea al mostrarlo: seria mostrar otra cifra de la guardada. */
    public function testUnMontoViejoConCentavosEnYenesLosMuestra(): void
    {
        self::assertSame('¥1,234.50 JPY', dinero_en_moneda(1234.5, 'JPY'));
    }

    public function testElFormularioDeUnaMonedaSinDecimalesPideEnteros(): void
    {
        $html = campo_monto('1500', 'JPY');
        self::assertStringContainsString('min="1"', $html);
        self::assertStringContainsString('step="1"', $html);
        self::assertStringContainsString('max="999999999999"', $html);

        $conDecimales = campo_monto('15.00', 'USD');
        self::assertStringContainsString('min="0.01"', $conDecimales);
        self::assertStringContainsString('step="0.01"', $conDecimales);
        self::assertSame(campo_monto('15.00'), $conDecimales, 'sin moneda (o con una de dos decimales) es el campo de siempre');
    }

    public function testUnaBoletaEnYenesNoAdmiteCentavos(): void
    {
        self::assertSame(
            'En JPY los montos van sin decimales.',
            BoletasController::errorDeLosCampos('Cuota', 1500.5, '2026-01-01', '2026-02-01', 'JPY')
        );
        self::assertNull(BoletasController::errorDeLosCampos('Cuota', 1500.0, '2026-01-01', '2026-02-01', 'JPY'));
        self::assertNull(BoletasController::errorDeLosCampos('Cuota', 1500.5, '2026-01-01', '2026-02-01', 'USD'));
        self::assertNull(BoletasController::errorDeLosCampos('Cuota', 1500.5, '2026-01-01', '2026-02-01'), 'sin moneda solo se chequea el rango');
    }

    /** El rango se chequea primero: un monto de cero en yenes es «monto invalido», no «sin decimales». */
    public function testElRangoVaAntesQueLosDecimales(): void
    {
        self::assertSame(
            'Completá todos los campos con un monto válido.',
            BoletasController::errorDeLosCampos('Cuota', 0.0, '2026-01-01', '2026-02-01', 'JPY')
        );
    }

    public function testUnPagoEnWonesNoAdmiteCentavos(): void
    {
        self::assertSame('En KRW los montos van sin decimales.', PagosController::errorDeLosCampos('2026-01-01', 'tarjeta', 100.25, 'KRW'));
        self::assertNull(PagosController::errorDeLosCampos('2026-01-01', 'tarjeta', 100.0, 'KRW'));
        self::assertNull(PagosController::errorDeLosCampos('2026-01-01', 'tarjeta', 100.25, 'EUR'));
    }
}
