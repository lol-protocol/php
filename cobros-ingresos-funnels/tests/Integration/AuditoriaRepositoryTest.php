<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Paginacion;
use App\Repositories\AuditoriaRepository;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Corre contra la base configurada por las env vars DB_*. La auditoria es de
 * solo insercion (no hay metodo de borrado a proposito): las filas que agregan
 * estos tests se deshacen con la transaccion de cada uno. Los que necesitan una
 * tabla conocida la vacian primero con un DELETE que tambien se deshace.
 */
final class AuditoriaRepositoryTest extends IntegracionTestCase
{
    public function testRegistrarQuedaPrimeroEnElHistorialPorSerElMasReciente(): void
    {
        $repo = new AuditoriaRepository();
        $detalle = 'Registro de prueba ' . uniqid();

        $repo->registrar(null, 'crear', 'boleta', 999999, $detalle);

        $filas = $repo->pagina()['filas'];

        self::assertNotEmpty($filas);
        self::assertSame($detalle, $filas[0]['detalle']);
        self::assertSame('crear', $filas[0]['accion']);
        self::assertSame('boleta', $filas[0]['entidad']);
        self::assertSame('Sistema', $filas[0]['usuario'], 'sin usuario_id asociado debe mostrar Sistema');
    }

    /** Inserta $cantidad filas de la entidad 'prueba_cursor' con entidad_id = 0..$cantidad-1; $creadoEn recibe el indice y da el creado_en. */
    private function insertar(int $cantidad, callable $creadoEn): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO auditoria (accion, entidad, entidad_id, detalle, creado_en)
             VALUES ('crear', 'prueba_cursor', :i, :detalle, :creado_en)"
        );
        for ($i = 0; $i < $cantidad; $i++) {
            $stmt->execute([':i' => $i, ':detalle' => "Fila {$i}", ':creado_en' => $creadoEn($i)]);
        }
    }

    /** @param array{filas: array<int, array<string, mixed>>, masAntiguas: ?string, masRecientes: ?string} $pagina @return list<int> */
    private static function ids(array $pagina): array
    {
        return array_map(static fn (array $fila): int => (int) $fila['entidad_id'], $pagina['filas']);
    }

    public function testConPocasFilasNoHayNadaMasAntiguoNiMasReciente(): void
    {
        Database::connection()->exec('DELETE FROM auditoria');
        $this->insertar(3, static fn (int $i): string => '2026-01-01 10:00:00');

        $pagina = (new AuditoriaRepository())->pagina();

        self::assertCount(3, $pagina['filas']);
        self::assertNull($pagina['masAntiguas']);
        self::assertNull($pagina['masRecientes']);
    }

    public function testLaPrimeraPaginaTraeElTamanioDePaginaYAvisaQueHayMasAntiguas(): void
    {
        Database::connection()->exec('DELETE FROM auditoria');
        $this->insertar(Paginacion::POR_PAGINA + 1, static fn (int $i): string => '2026-01-01 10:00:00');

        $pagina = (new AuditoriaRepository())->pagina();

        self::assertCount(Paginacion::POR_PAGINA, $pagina['filas']);
        self::assertNotNull($pagina['masAntiguas']);
        self::assertNull($pagina['masRecientes'], 'es la primera: nada mas reciente');
    }

    /**
     * Este test se salteaba en todas las corridas cuando la paginacion era por
     * LIMIT/OFFSET: dependia de que el seed dejara mas de una pagina, y el seed
     * no audita nada. Ahora arma su propia tabla: tres paginas (25, 25 y 7), con
     * las primeras 30 filas empatadas en creado_en -como lo que escribe una sola
     * transaccion, y el empate cruza el borde entre la pagina 1 y la 2- y el
     * resto en minutos distintos. Se recorre hacia las mas antiguas y se vuelve:
     * no puede faltar ni repetirse ninguna fila, ni cambiar el orden, ni cambiar
     * las paginas al volver.
     */
    public function testSeRecorreTodoElHistorialHaciaAtrasYDeVueltaSinRepetirNiPerderFilas(): void
    {
        $db = Database::connection();
        $db->exec('DELETE FROM auditoria');
        $total = 2 * Paginacion::POR_PAGINA + 7;
        $this->insertar($total, static fn (int $i): string => $i < 30
            ? '2026-01-01 10:00:00'
            : sprintf('2026-01-01 09:%02d:00', 59 - ($i - 30)));
        $repo = new AuditoriaRepository();

        $esperado = array_map('intval', $db->query('SELECT entidad_id FROM auditoria ORDER BY creado_en DESC, id DESC')->fetchAll(PDO::FETCH_COLUMN));
        self::assertCount($total, $esperado);

        $pagina = $repo->pagina();
        $paginas = [self::ids($pagina)];
        while ($pagina['masAntiguas'] !== null) {
            $pagina = $repo->pagina($pagina['masAntiguas']);
            $paginas[] = self::ids($pagina);
        }
        self::assertSame([Paginacion::POR_PAGINA, Paginacion::POR_PAGINA, 7], array_map('count', $paginas));
        self::assertSame($esperado, array_merge(...$paginas), 'hacia las mas antiguas: todas las filas, en orden, sin repetir');
        self::assertNull($pagina['masAntiguas'], 'la ultima no tiene mas antiguas');

        $vueltas = [self::ids($pagina)];
        while ($pagina['masRecientes'] !== null) {
            $pagina = $repo->pagina(null, $pagina['masRecientes']);
            array_unshift($vueltas, self::ids($pagina));
        }
        self::assertSame($paginas, $vueltas, 'al volver hacia las mas recientes se ven las mismas paginas');
    }

    public function testSubirHastaLoMasRecienteDevuelveLaPrimeraPaginaEnteraYNoUnaCortada(): void
    {
        Database::connection()->exec('DELETE FROM auditoria');
        $this->insertar(Paginacion::POR_PAGINA + 10, static fn (int $i): string => sprintf('2026-01-01 08:%02d:00', $i));
        $repo = new AuditoriaRepository();

        $primera = $repo->pagina();
        $tercera = $primera['filas'][2];

        // Solo hay dos filas mas recientes que la tercera: no se muestra una pagina de dos.
        $subida = $repo->pagina(null, AuditoriaRepository::cursorDe($tercera));

        self::assertSame($primera['filas'], $subida['filas']);
        self::assertNull($subida['masRecientes']);
    }

    public function testElCursorEsExactoAlMicrosegundo(): void
    {
        Database::connection()->exec('DELETE FROM auditoria');
        $this->insertar(3, static fn (int $i): string => '2026-01-01 10:00:00.00000' . (3 - $i));
        $repo = new AuditoriaRepository();

        $primera = $repo->pagina();
        self::assertSame([0, 1, 2], self::ids($primera), 'del microsegundo mas nuevo al mas viejo');

        $despuesDeLaPrimera = $repo->pagina(AuditoriaRepository::cursorDe($primera['filas'][0]));
        self::assertSame([1, 2], self::ids($despuesDeLaPrimera), 'el cursor no se salta ni repite filas del mismo segundo');
    }

    public function testElCursorTieneElFormatoDeLaUrl(): void
    {
        $fila = ['creado_en' => '2026-10-07 00:12:34.567891', 'id' => 123];

        self::assertSame('2026-10-07 00:12:34.567891,123', AuditoriaRepository::cursorDe($fila));
    }

    /** @return iterable<string, array{string}> */
    public static function cursoresInvalidos(): iterable
    {
        yield 'texto cualquiera' => ['basura'];
        yield 'vacio' => [''];
        yield 'sin id' => ['2026-10-07 00:00:00'];
        yield 'id que no es un numero' => ['2026-10-07 00:00:00,abc'];
        yield 'id enorme' => ['2026-10-07 00:00:00,99999999999999999999'];
        yield 'dia que no existe' => ['2026-02-30 00:00:00,1'];
        yield 'mes y hora imposibles' => ['2026-13-45 99:99:99,1'];
        yield 'fecha sin hora' => ['2026-10-07,1'];
        yield 'intento de inyeccion' => ["2026-10-07 00:00:00,1; DROP TABLE auditoria"];
    }

    /** Nadie tiene que poder romper la pantalla editando la URL: un cursor que no es uno equivale a no pasar ninguno. */
    #[DataProvider('cursoresInvalidos')]
    public function testUnCursorInvalidoMuestraLaPrimeraPagina(string $cursor): void
    {
        Database::connection()->exec('DELETE FROM auditoria');
        $this->insertar(Paginacion::POR_PAGINA + 5, static fn (int $i): string => sprintf('2026-01-01 08:%02d:00', $i));
        $repo = new AuditoriaRepository();

        $primera = $repo->pagina();

        self::assertSame($primera, $repo->pagina($cursor, null));
        self::assertSame($primera, $repo->pagina(null, $cursor));
    }

    public function testUnCursorMasAntiguoQueTodoMuestraLaPrimeraPagina(): void
    {
        Database::connection()->exec('DELETE FROM auditoria');
        $this->insertar(5, static fn (int $i): string => '2026-01-01 10:00:00');
        $repo = new AuditoriaRepository();

        self::assertSame($repo->pagina(), $repo->pagina('2000-01-01 00:00:00,1'), 'no hay nada mas antiguo: se muestra el principio');
    }

    public function testSiVienenLosDosCursoresMandaAntes(): void
    {
        Database::connection()->exec('DELETE FROM auditoria');
        $this->insertar(Paginacion::POR_PAGINA + 10, static fn (int $i): string => sprintf('2026-01-01 08:%02d:00', $i));
        $repo = new AuditoriaRepository();
        $primera = $repo->pagina();
        $cursor = (string) $primera['masAntiguas'];

        self::assertSame($repo->pagina($cursor), $repo->pagina($cursor, AuditoriaRepository::cursorDe($primera['filas'][5])));
    }

    public function testAuditarDentroDeUnaTransaccionRegistraLaFila(): void
    {
        $detalle = 'Auditoria en transaccion ' . uniqid();

        AuditoriaRepository::auditar('crear', 'prueba', 1, $detalle);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM auditoria WHERE detalle = :detalle');
        $stmt->execute([':detalle' => $detalle]);
        self::assertSame(1, (int) $stmt->fetchColumn());
    }
}
