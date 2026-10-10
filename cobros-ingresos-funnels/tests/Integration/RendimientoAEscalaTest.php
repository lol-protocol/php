<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\IngresosYCobrosRepository;

/**
 * Lo que tarda una pantalla con 100 mil boletas no se ve con las ~130 que deja
 * el seed, pero lo que gasta de memoria si se puede medir con pocas: estos tests
 * cargan 30 mil boletas (la mitad pagadas) y exigen que la pantalla no las traiga
 * a PHP. Dos casos reales a escala: el filtro de estado de Cobros traia todo el
 * rango y lo filtraba en un array (con 120 mil boletas, 128 MB no alcanzaban y la
 * pagina salia en blanco con un 500), y la cartera por antiguedad leia todas las
 * boletas para sumarlas en un foreach (58 MB con 117 mil).
 *
 * Es transaccional como los demas: las 30 mil filas se deshacen al terminar.
 */
final class RendimientoAEscalaTest extends IntegracionTestCase
{
    private const BOLETAS = 30000;
    private const MONTO = '100.00';
    private const EMISION = '1902-02-02';

    /** Carga las boletas del cliente #1 (la mitad pagadas) y devuelve la moneda en que quedaron. */
    private function cargarBoletas(): string
    {
        $cliente = (new ClienteRepository())->porId(1);
        self::assertNotNull($cliente, 'este test asume que el cliente #1 existe (lo trae el seed)');
        $moneda = $cliente['moneda_codigo'];

        $db = Database::connection();
        $db->exec(
            "INSERT INTO boletas (cliente_id, concepto, monto, moneda_codigo, fecha_emision, fecha_vencimiento)
             SELECT 1, 'Test de escala', " . self::MONTO . ", '{$moneda}', DATE '" . self::EMISION . "', DATE '1902-03-04'
             FROM generate_series(1, " . self::BOLETAS . ')'
        );
        // Las de id par, pagadas completas; el resto queda con todo el saldo pendiente y vencido desde hace anios.
        $db->exec(
            "INSERT INTO pagos (boleta_id, cliente_id, monto, moneda_codigo, fecha_pago, metodo)
             SELECT id, cliente_id, monto, moneda_codigo, DATE '1902-02-10', 'transferencia'
             FROM boletas WHERE concepto = 'Test de escala' AND id % 2 = 0"
        );

        return $moneda;
    }

    /** Bytes de memoria que sube el pico mientras corre $operacion. */
    private function picoDeMemoria(callable $operacion): int
    {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $base = memory_get_usage();
        $operacion();

        return memory_get_peak_usage() - $base;
    }

    public function testElFiltroDeEstadoPaginaEnSqlYNoTraeElRangoCompletoAPhp(): void
    {
        $this->cargarBoletas();
        $repo = new BoletaRepository();
        $listado = null;

        $pico = $this->picoDeMemoria(function () use ($repo, &$listado): void {
            $listado = $repo->listado(self::EMISION, self::EMISION, 'pagada');
        });

        self::assertIsArray($listado);
        self::assertSame(self::BOLETAS / 2, $listado['total']);
        self::assertCount(\App\Paginacion::POR_PAGINA, $listado['filas']);
        self::assertSame((int) ceil(self::BOLETAS / 2 / \App\Paginacion::POR_PAGINA), $listado['totalPaginas']);
        self::assertLessThan(
            8 * 1024 * 1024,
            $pico,
            sprintf('filtrar por estado gasto %.1f MB: esta trayendo las %d boletas a PHP', $pico / 1048576, self::BOLETAS)
        );
    }

    public function testLaCarteraPorAntiguedadSeSumaEnSqlYNoTraeLasBoletasAPhp(): void
    {
        $repo = new IngresosYCobrosRepository();
        $antes = $repo->carteraPorAntiguedad();
        $moneda = $this->cargarBoletas();
        $despues = [];

        $pico = $this->picoDeMemoria(function () use ($repo, &$despues): void {
            $despues = $repo->carteraPorAntiguedad();
        });

        $tasa = (float) Database::connection()->query("SELECT tasa_a_usd FROM monedas WHERE codigo = '{$moneda}'")->fetchColumn();
        $pendienteEnUsd = (self::BOLETAS / 2) * (float) self::MONTO * $tasa;
        self::assertEqualsWithDelta(
            $pendienteEnUsd,
            $despues['61+ días'] - $antes['61+ días'],
            0.05,
            'las boletas impagas de 1902 suman su saldo en el tramo mas viejo'
        );
        foreach (['Al día', '1-30 días', '31-60 días'] as $tramo) {
            self::assertEqualsWithDelta($antes[$tramo], $despues[$tramo], 0.05, "el tramo {$tramo} no tiene que cambiar");
        }
        self::assertLessThan(
            3 * 1024 * 1024,
            $pico,
            sprintf('la cartera gasto %.1f MB: esta leyendo todas las boletas en PHP', $pico / 1048576)
        );
    }
}
