<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Paginacion;
use App\Repositories\BoletaRepository;
use App\Repositories\CobranzaRepository;
use App\Repositories\PagoRepository;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Lo que se exporta es lo que la pantalla mostraria en TODAS sus paginas, con los mismos
 * filtros y en el mismo orden: las dos salen de la misma consulta (ver consultaDelListado()
 * de cada repositorio). Aca se comprueba comparando una exportacion con las paginas del
 * listado, sobre los datos de ejemplo.
 */
final class ExportacionDeListadosTest extends IntegracionTestCase
{
    private const DESDE = '2000-01-01';
    private const HASTA = '2100-12-31';

    /**
     * Las filas de todas las paginas de un listado.
     *
     * @param callable(int): array{filas: array<int, array<string, mixed>>, totalPaginas: int} $pagina
     * @return list<array<string, mixed>>
     */
    private static function todasLasPaginas(callable $pagina): array
    {
        $filas = [];
        $numero = 1;
        do {
            $listado = $pagina($numero);
            array_push($filas, ...$listado['filas']);
            $numero++;
        } while ($numero <= $listado['totalPaginas']);

        return $filas;
    }

    /** @return array<string, array{?string, ?string}> estado, cliente */
    public static function filtrosDeBoletas(): array
    {
        return [
            'sin filtros' => [null, null],
            'solo pagadas' => ['pagada', null],
            'solo vencidas' => ['vencida', null],
            'solo anuladas' => ['anulada', null],
            'por cliente' => [null, 'a'],
            'estado y cliente' => ['pendiente', 'e'],
        ];
    }

    #[DataProvider('filtrosDeBoletas')]
    public function testLasBoletasExportadasSonLasDeTodasLasPaginasDelListado(?string $estado, ?string $cliente): void
    {
        $repo = new BoletaRepository();

        $paginadas = self::todasLasPaginas(fn (int $p): array => $repo->listado(self::DESDE, self::HASTA, $estado, $cliente, $p));
        $exportadas = iterator_to_array($repo->exportar(self::DESDE, self::HASTA, $estado, $cliente), false);

        self::assertSame(
            array_map(static fn (array $f): array => [$f['id'], $f['estado'], $f['saldo']], $paginadas),
            array_map(static fn (array $f): array => [$f['id'], $f['estado'], $f['saldo']], $exportadas),
            'mismas boletas, en el mismo orden y con el mismo estado calculado'
        );
    }

    public function testElFiltroDeEstadoDeVerdadFiltra(): void
    {
        $exportadas = iterator_to_array((new BoletaRepository())->exportar(self::DESDE, self::HASTA, 'pagada', null), false);

        self::assertNotSame([], $exportadas, 'los datos de ejemplo tienen boletas pagadas');
        foreach ($exportadas as $boleta) {
            self::assertSame('pagada', $boleta['estado']);
        }
        self::assertLessThan(
            count(iterator_to_array((new BoletaRepository())->exportar(self::DESDE, self::HASTA), false)),
            count($exportadas),
            'y no todas son pagadas'
        );
    }

    /** @return array<string, array{?string}> el filtro de cliente */
    public static function filtrosDePagos(): array
    {
        return ['sin filtro' => [null], 'por cliente' => ['a']];
    }

    #[DataProvider('filtrosDePagos')]
    public function testLosPagosExportadosSonLosDeTodasLasPaginasDelListado(?string $cliente): void
    {
        $repo = new PagoRepository();

        $paginados = self::todasLasPaginas(fn (int $p): array => $repo->listado(self::DESDE, self::HASTA, $cliente, $p));
        $exportados = iterator_to_array($repo->exportar(self::DESDE, self::HASTA, $cliente), false);

        self::assertGreaterThan(Paginacion::POR_PAGINA, count($exportados), 'son mas que una pagina: eso es lo que prueba el test');
        self::assertSame(array_column($paginados, 'id'), array_column($exportados, 'id'));
    }

    /** @return array<string, array{string, string}> */
    public static function alcancesYOrdenes(): array
    {
        $casos = [];
        foreach (array_keys(CobranzaRepository::ALCANCES) as $alcance) {
            foreach (array_keys(CobranzaRepository::ORDENES) as $orden) {
                $casos["{$alcance} / {$orden}"] = [$alcance, $orden];
            }
        }

        return $casos;
    }

    #[DataProvider('alcancesYOrdenes')]
    public function testLaCobranzaExportadaEsLaDeTodasLasPaginasDeLaLista(string $alcance, string $orden): void
    {
        $repo = new CobranzaRepository();

        $paginados = self::todasLasPaginas(fn (int $p): array => $repo->listado($alcance, $orden, $p));
        $exportados = iterator_to_array($repo->exportar($alcance, $orden), false);

        self::assertNotSame([], $exportados);
        self::assertSame(array_column($paginados, 'cliente_id'), array_column($exportados, 'cliente_id'));
    }
}
