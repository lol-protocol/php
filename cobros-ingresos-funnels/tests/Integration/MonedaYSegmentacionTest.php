<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repositories\ClienteRepository;
use App\Repositories\MonedaRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php database/seed.php` (mismas variables) para tener datos.
 */
final class MonedaYSegmentacionTest extends TestCase
{
    public function testSimboloDeMonedaConocida(): void
    {
        self::assertSame('$', MonedaRepository::simbolo('USD'));
    }

    public function testSimboloPorDefectoParaCodigoDesconocidoEsElMismoCodigo(): void
    {
        self::assertSame('ZZZ', MonedaRepository::simbolo('ZZZ'));
    }

    public function testMoneyMonedaCombinaSimboloMontoYCodigo(): void
    {
        $texto = money_moneda(1234.5, 'USD');
        self::assertSame('$1,234.50 USD', $texto);
    }

    public function testTopPorDimensionEstaOrdenadoDescendentePorFacturacion(): void
    {
        $filas = (new ClienteRepository())->topPorPais(5);

        $totales = array_column($filas, 'total_facturado');
        $ordenados = $totales;
        rsort($ordenados);
        self::assertSame($ordenados, $totales, 'debe venir ordenado de mayor a menor facturacion');

        foreach ($filas as $fila) {
            self::assertGreaterThanOrEqual(0.0, (float) $fila['total_facturado']);
            self::assertGreaterThan(0, (int) $fila['clientes']);
        }
    }
}
