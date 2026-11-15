<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\ReglasDeBoleta;
use App\ReglasDePago;
use App\Repositories\MonedaRepository;

/**
 * Los decimales de cada moneda (monedas.decimales, migracion 014): el yen, el won y el peso chileno no
 * tienen fraccion; el dinar kuwaiti y las demas de 3 decimales los guardan completos desde la migracion
 * 019 (las columnas de monto son NUMERIC(15, 3)).
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

    /** El dinar kuwaiti tiene tres decimales en ISO 4217 y la aplicacion los usa completos. */
    public function testUnaMonedaDeTresDecimalesSeGuardaYSeMuestraConTres(): void
    {
        self::assertSame(3, MonedaRepository::decimalesPorMoneda()['KWD']);
        foreach (['KWD', 'BHD', 'OMR', 'TND', 'JOD', 'IQD', 'LYD'] as $moneda) {
            self::assertSame(3, MonedaRepository::decimales($moneda), $moneda);
        }
        self::assertSame(MonedaRepository::simbolo('KWD') . '1,234.500 KWD', dinero_en_moneda(1234.5, 'KWD'));
        self::assertSame(MonedaRepository::simbolo('KWD') . '1.505 KWD', dinero_en_moneda(1.505, 'KWD'));
    }

    public function testElFormularioDeUnaMonedaDeTresDecimalesPideMilesimas(): void
    {
        $html = campo_monto('1.505', 'KWD');
        self::assertStringContainsString('min="0.001"', $html);
        self::assertStringContainsString('step="0.001"', $html);
        self::assertStringContainsString('max="999999999999.999"', $html);
        self::assertStringContainsString('value="1.505"', $html);
    }

    /** Las reglas de dinero no pierden la milesima: la boleta de 1.505 con 1.005 cobrados deja 0.500, y se paga justo. */
    public function testLasReglasDeDineroComparanMilesimas(): void
    {
        $boleta = ['monto' => 1.505, 'pagado' => 1.005, 'saldo' => 0.5];
        self::assertTrue(ReglasDePago::montoNoSuperaElSaldo(0.5, $boleta));
        self::assertFalse(ReglasDePago::montoNoSuperaElSaldo(0.501, $boleta), 'una milesima de mas ya es sobrecobrar');
        self::assertTrue(\App\ReglasDeBoleta::montoCubreLoYaCobrado(1.005, $boleta));
        self::assertFalse(\App\ReglasDeBoleta::montoCubreLoYaCobrado(1.004, $boleta), 'una milesima por debajo de lo cobrado');
        self::assertNull(ReglasDeBoleta::errorDeLosCampos('Cuota', 1.505, '2026-01-01', '2026-02-01', 'KWD'));
        self::assertNull(ReglasDeBoleta::errorDeLosCampos('Cuota', 0.001, '2026-01-01', '2026-02-01', 'KWD'), 'la unidad menor del dinar es la milesima');
        self::assertSame(
            'Completá todos los campos con un monto válido.',
            ReglasDeBoleta::errorDeLosCampos('Cuota', 0.001, '2026-01-01', '2026-02-01', 'USD'),
            'en dolares una milesima no es un monto'
        );
    }

    /** Un saldo de un centavo o menos cuenta como cero; el borde se mide en milesimas. */
    public function testElBordeDelCentavoEnUnaMonedaDeTresDecimales(): void
    {
        self::assertSame('pagada', \App\EstadoBoleta::calcular(1.505, 1.495, '2099-01-01')['estado'], 'saldo 0.010');
        self::assertSame('parcial', \App\EstadoBoleta::calcular(1.505, 1.494, '2099-01-01')['estado'], 'saldo 0.011');
        self::assertSame(0.011, \App\EstadoBoleta::calcular(1.505, 1.494, '2099-01-01')['saldo']);
    }

    public function testUnMontoDeTresDecimalesSoloSeLeeEnUnaMonedaDeTres(): void
    {
        self::assertSame(1.505, \App\Dinero::deTexto('1.505', 3));
        self::assertSame(1.5, \App\Dinero::deTexto('1.500', 3), 'en dinares, 1.500 son uno y medio: no hay punto de miles que confundir');
        $this->expectException(\InvalidArgumentException::class);
        \App\Dinero::deTexto('1.500', 2);
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
            ReglasDeBoleta::errorDeLosCampos('Cuota', 1500.5, '2026-01-01', '2026-02-01', 'JPY')
        );
        self::assertNull(ReglasDeBoleta::errorDeLosCampos('Cuota', 1500.0, '2026-01-01', '2026-02-01', 'JPY'));
        self::assertNull(ReglasDeBoleta::errorDeLosCampos('Cuota', 1500.5, '2026-01-01', '2026-02-01', 'USD'));
        self::assertNull(ReglasDeBoleta::errorDeLosCampos('Cuota', 1500.5, '2026-01-01', '2026-02-01'), 'sin moneda solo se chequea el rango');
    }

    /** El rango se chequea primero: un monto de cero en yenes es «monto invalido», no «sin decimales». */
    public function testElRangoVaAntesQueLosDecimales(): void
    {
        self::assertSame(
            'Completá todos los campos con un monto válido.',
            ReglasDeBoleta::errorDeLosCampos('Cuota', 0.0, '2026-01-01', '2026-02-01', 'JPY')
        );
    }

    public function testUnPagoEnWonesNoAdmiteCentavos(): void
    {
        self::assertSame('En KRW los montos van sin decimales.', ReglasDePago::errorDeLosCampos('2026-01-01', 'tarjeta', 100.25, 'KRW'));
        self::assertNull(ReglasDePago::errorDeLosCampos('2026-01-01', 'tarjeta', 100.0, 'KRW'));
        self::assertNull(ReglasDePago::errorDeLosCampos('2026-01-01', 'tarjeta', 100.25, 'EUR'));
    }
}
