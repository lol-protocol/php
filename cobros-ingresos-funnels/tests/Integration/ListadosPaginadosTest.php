<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Paginacion;
use App\Repositories\ClienteRepository;
use App\Repositories\PagoRepository;

/**
 * El esqueleto de los listados paginados (Paginacion::consultar) y los dos listados que lo usan y no
 * tenian prueba propia: ClienteRepository::buscar() y PagoRepository::listado(). El de boletas, que
 * tambien lo usa, se prueba en BoletaRepositoryTest.
 *
 * Corre contra la base configurada por las env vars DB_*, con los datos de ejemplo.
 */
final class ListadosPaginadosTest extends IntegracionTestCase
{
    private const PREFIJO = 'ZZZZ Paginado';

    /**
     * Clientes de relleno que ordenan despues de los del seed y se buscan por su nombre. Numerados
     * ('ZZZZ Paginado 0001', 0002...) o, con $numerados en false, todos con el mismo nombre.
     */
    private function crearClientes(int $cuantos, string $nombre = self::PREFIJO, bool $numerados = true): void
    {
        $nombreSql = $numerados ? "CAST(:nombre AS text) || ' ' || lpad(g::text, 4, '0')" : 'CAST(:nombre AS text)';
        Database::connection()->prepare(
            "INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais_codigo, ciudad, idioma, genero, fecha_nacimiento)
             SELECT {$nombreSql}, lower(regexp_replace(CAST(:nombre AS text), '[^a-zA-Z0-9]+', '-', 'g')) || '-' || g || '@example.com',
                    'general', CURRENT_DATE, 'AR', 'Rosario', 'Espanol', 'No especifica', DATE '1990-01-01'
             FROM generate_series(1, :cuantos) g"
        )->execute([':nombre' => $nombre, ':cuantos' => $cuantos]);
    }

    private static function contar(string $sql, array $parametros = []): int
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn();
    }

    public function testBuscarSinTextoTrae25PorPaginaYElTotalDeLaTabla(): void
    {
        $total = self::contar('SELECT COUNT(*) FROM clientes');

        $pagina = (new ClienteRepository())->buscar();

        self::assertSame($total, $pagina['total']);
        self::assertSame((int) ceil($total / Paginacion::POR_PAGINA), $pagina['totalPaginas']);
        self::assertSame(1, $pagina['pagina']);
        self::assertCount(min($total, Paginacion::POR_PAGINA), $pagina['filas']);
    }

    public function testBuscarFiltraPorNombreOEmailSinMirarMayusculasYCuentaSoloLoFiltrado(): void
    {
        $this->crearClientes(30);
        $repo = new ClienteRepository();

        self::assertSame(30, $repo->buscar(self::PREFIJO)['total']);
        self::assertSame(30, $repo->buscar('zzzz PAGINADO')['total'], 'ILIKE: no distingue mayusculas');
        self::assertSame(1, $repo->buscar('zzzz-paginado-7@')['total'], 'tambien busca en el email');
        self::assertSame(0, $repo->buscar('Nadie se llama asi ' . uniqid())['total']);
    }

    public function testBuscarPaginaOrdenaPorNombreYNoRepiteFilasEntrePaginas(): void
    {
        $this->crearClientes(30);
        $repo = new ClienteRepository();

        $primera = $repo->buscar(self::PREFIJO, 1);
        $segunda = $repo->buscar(self::PREFIJO, 2);

        self::assertSame(2, $primera['totalPaginas']);
        self::assertCount(Paginacion::POR_PAGINA, $primera['filas']);
        self::assertCount(30 - Paginacion::POR_PAGINA, $segunda['filas']);

        $nombres = array_column([...$primera['filas'], ...$segunda['filas']], 'nombre');
        $ordenados = $nombres;
        sort($ordenados);
        self::assertSame($ordenados, $nombres, 'ordenados por nombre, una pagina detras de la otra');
        self::assertCount(30, array_unique(array_column([...$primera['filas'], ...$segunda['filas']], 'id')));
        self::assertArrayHasKey('pais_nombre', $primera['filas'][0], 'el listado trae el pais, por el join');
    }

    public function testBuscarDesempataPorIdCuandoElNombreSeRepite(): void
    {
        $this->crearClientes(3, 'ZZZZ Empate', numerados: false);

        $ids = array_column((new ClienteRepository())->buscar('ZZZZ Empate')['filas'], 'id');

        self::assertCount(3, $ids);
        $ordenados = $ids;
        sort($ordenados);
        self::assertSame($ordenados, $ids, 'a igual nombre, el de menor id primero (si no, el orden no esta definido)');
    }

    public function testUnaPaginaFueraDeRangoSeAcotaALaUltimaYUnaMenorQueUnoALaPrimera(): void
    {
        $this->crearClientes(30);
        $repo = new ClienteRepository();
        $ultima = $repo->buscar(self::PREFIJO, 2);

        $mas = $repo->buscar(self::PREFIJO, 9999);
        self::assertSame(2, $mas['pagina']);
        self::assertSame($ultima['filas'], $mas['filas']);

        foreach ([0, -3] as $pedida) {
            $primera = $repo->buscar(self::PREFIJO, $pedida);
            self::assertSame(1, $primera['pagina'], "pagina {$pedida}");
            self::assertCount(Paginacion::POR_PAGINA, $primera['filas']);
        }
    }

    public function testSinResultadosEsUnaPaginaVaciaConTotalCeroYUnaSolaPagina(): void
    {
        $pagina = (new ClienteRepository())->buscar('Nadie se llama asi ' . uniqid(), 7);

        self::assertSame([], $pagina['filas']);
        self::assertSame(0, $pagina['total']);
        self::assertSame(1, $pagina['totalPaginas']);
        self::assertSame(1, $pagina['pagina']);
    }

    public function testConsultarCuentaSobreOtroFromCuandoSeLoPidenYSobreElMismoCuandoNo(): void
    {
        $this->crearClientes(30);
        $db = Database::connection();
        $comun = [
            'columnas' => 'c.id, c.nombre',
            'orden' => 'c.id',
            'params' => [':q' => self::PREFIJO . '%'],
            'pagina' => 2,
        ];

        $conOtroConteo = Paginacion::consultar(
            $db,
            ...$comun,
            desde: 'clientes c JOIN paises p ON p.codigo = c.pais_codigo WHERE c.nombre LIKE :q',
            desdeDelConteo: 'clientes c WHERE c.nombre LIKE :q',
        );
        $sinOtroConteo = Paginacion::consultar(
            $db,
            ...$comun,
            desde: 'clientes c JOIN paises p ON p.codigo = c.pais_codigo WHERE c.nombre LIKE :q',
        );

        self::assertSame(30, $conOtroConteo['total']);
        self::assertCount(5, $conOtroConteo['filas']);
        self::assertSame($conOtroConteo, $sinOtroConteo, 'el join a paises no cambia ni el total ni las filas');
    }

    public function testListadoDePagosFiltraPorRangoYPorClienteYCuentaLoMismoQueTrae(): void
    {
        $repo = new PagoRepository();
        $hay = self::contar("SELECT COUNT(*) FROM pagos WHERE fecha_pago BETWEEN DATE '2000-01-01' AND DATE '2100-01-01'");
        self::assertGreaterThan(Paginacion::POR_PAGINA, $hay, 'el seed tiene que dar mas de una pagina de pagos para que esta prueba diga algo');

        $todos = $repo->listado('2000-01-01', '2100-01-01');
        self::assertSame($hay, $todos['total']);
        self::assertCount(Paginacion::POR_PAGINA, $todos['filas']);
        self::assertSame((int) ceil($hay / Paginacion::POR_PAGINA), $todos['totalPaginas']);

        // Fuera de rango no hay nada.
        self::assertSame(0, $repo->listado('2099-01-01', '2099-12-31')['total']);

        // Por cliente: solo sus pagos, y el total es el de ese filtro (no el de toda la tabla).
        $nombre = $todos['filas'][0]['cliente'];
        $esperados = self::contar(
            "SELECT COUNT(*) FROM pagos p JOIN clientes c ON c.id = p.cliente_id
             WHERE p.fecha_pago BETWEEN DATE '2000-01-01' AND DATE '2100-01-01' AND c.nombre ILIKE :n",
            [':n' => "%{$nombre}%"]
        );
        $delCliente = $repo->listado('2000-01-01', '2100-01-01', $nombre);
        self::assertSame($esperados, $delCliente['total']);
        self::assertLessThan($hay, $delCliente['total']);
        foreach ($delCliente['filas'] as $pago) {
            self::assertStringContainsString(mb_strtolower($nombre), mb_strtolower($pago['cliente']));
        }
    }

    public function testListadoDePagosVaDelMasRecienteAlMasViejoYLasPaginasJuntasSonElTotalSinRepetir(): void
    {
        $repo = new PagoRepository();
        $primera = $repo->listado('2000-01-01', '2100-01-01', null, 1);

        $ids = [];
        $anteriorFecha = null;
        $anteriorId = null;
        for ($pagina = 1; $pagina <= $primera['totalPaginas']; $pagina++) {
            foreach ($repo->listado('2000-01-01', '2100-01-01', null, $pagina)['filas'] as $pago) {
                if ($anteriorFecha !== null) {
                    $bien = $pago['fecha_pago'] < $anteriorFecha
                        || ($pago['fecha_pago'] === $anteriorFecha && $pago['id'] < $anteriorId);
                    self::assertTrue($bien, "pago #{$pago['id']} ({$pago['fecha_pago']}) despues de #{$anteriorId} ({$anteriorFecha})");
                }
                $anteriorFecha = $pago['fecha_pago'];
                $anteriorId = $pago['id'];
                $ids[] = $pago['id'];
            }
        }

        self::assertCount($primera['total'], $ids, 'las paginas, juntas, tienen exactamente el total de filas');
        self::assertCount($primera['total'], array_unique($ids), 'sin repetir ninguna');
    }

    public function testElListadoDePagosIncluyeLosAnuladosMarcados(): void
    {
        $anulados = self::contar('SELECT COUNT(*) FROM pagos WHERE anulada');
        if ($anulados === 0) {
            self::markTestSkipped('No hay pagos anulados seedeados.');
        }

        $repo = new PagoRepository();
        $vistos = 0;
        for ($pagina = 1, $paginas = $repo->listado('2000-01-01', '2100-01-01')['totalPaginas']; $pagina <= $paginas; $pagina++) {
            foreach ($repo->listado('2000-01-01', '2100-01-01', null, $pagina)['filas'] as $pago) {
                $vistos += $pago['anulada'] ? 1 : 0;
            }
        }

        self::assertSame($anulados, $vistos);
    }
}
