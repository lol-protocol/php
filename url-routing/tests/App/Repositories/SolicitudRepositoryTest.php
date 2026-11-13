<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\SolicitudRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/** The solicitudes of access, correction or erasure of a persona's data, on every available engine. */
class SolicitudRepositoryTest extends TestCase
{
    private const ELLA = 6128479601;
    private const OTRA = 6128479604;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    #[DataProvider('drivers')]
    public function testASolicitudIsStoredPendingAndNumberedOneAfterAnother(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        $repo = new SolicitudRepository($db);

        $uno = $repo->crear(self::ELLA, 'acceso', 'Quiero ver mis datos', 'ella@example.com', 1_700_000_000);
        $dos = $repo->crear(self::ELLA, 'supresion', 'Que se borren', 'ella@example.com');

        $this->assertSame([1, 2], [$uno, $dos]);
        $fila = $repo->ver($uno);
        $this->assertSame([self::ELLA, 'acceso', 'pendiente', 'ella@example.com'], [(int)$fila['persona_id'], $fila['derecho'], $fila['estado'], $fila['contacto']]);
        $this->assertNull($fila['cerrada_en']);
        $this->assertSame(2, $repo->pendientesDe(self::ELLA));
        $this->assertSame(0, $repo->pendientesDe(self::OTRA));
        $this->assertNull($repo->ver(99));
    }

    #[DataProvider('drivers')]
    public function testOnlyTheRightsOfTheFormCanBeAskedFor(string $driver): void
    {
        $repo = new SolicitudRepository(TestDatabases::fresh($driver, 'genealogy'));

        foreach (['', 'retiro', 'ver todo', 'ACCESO'] as $derecho) {
            try {
                $repo->crear(self::ELLA, $derecho, 'x', 'a@example.com');
                $this->fail("{$driver}: «{$derecho}» must be refused");
            } catch (\InvalidArgumentException) {
            }
        }
        $this->assertSame(0, $repo->pendientesDe(self::ELLA));
    }

    #[DataProvider('drivers')]
    public function testTheListOfWhatWaitsSaysNothingOfWhoAskedNorWhatTheyWrote(string $driver): void
    {
        $repo = new SolicitudRepository(TestDatabases::fresh($driver, 'genealogy'));
        $ahora = 1_700_000_000;
        $repo->crear(self::ELLA, 'acceso', 'Mi texto privado', 'ella@example.com', $ahora - 5 * 3600 - 10);
        $cerrada = $repo->crear(self::OTRA, 'rectificacion', 'Otro texto', 'otra@example.com', $ahora - 7200);
        $repo->cerrar($cerrada, 'atender', 'Hecho');

        $lista = $repo->pendientes($ahora);

        $this->assertCount(1, $lista, 'the closed one is not there');
        $this->assertSame(['id' => 1, 'persona_id' => self::ELLA, 'derecho' => 'acceso', 'creado_en' => date('Y-m-d H:i:s', $ahora - 5 * 3600 - 10), 'horas' => 5], $lista[0]);
        $this->assertStringNotContainsString('ella@example.com', json_encode($lista, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Mi texto', json_encode($lista, JSON_THROW_ON_ERROR));
    }

    #[DataProvider('drivers')]
    public function testClosingWorksOnceAndTheFirstAnswerStays(string $driver): void
    {
        $repo = new SolicitudRepository(TestDatabases::fresh($driver, 'genealogy'));
        $n = $repo->crear(self::ELLA, 'acceso', 'x', 'a@example.com');

        $this->assertTrue($repo->cerrar($n, 'rechazar', 'No se pudo comprobar quién pide', 1_700_000_000));
        $this->assertFalse($repo->cerrar($n, 'atender', 'Otra cosa'), 'closed already');
        $this->assertFalse($repo->cerrar(99, 'atender', null), 'there is none');

        $fila = $repo->ver($n);
        $this->assertSame(['rechazada', 'No se pudo comprobar quién pide'], [$fila['estado'], $fila['nota']]);
        $this->assertSame(0, $repo->pendientesDe(self::ELLA));
        $this->expectException(\InvalidArgumentException::class);
        $repo->cerrar($n, 'borrar', null);
    }

    #[DataProvider('drivers')]
    public function testErasingTheDataOfOldClosedOnesKeepsTheRestAndHappensOnce(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        $repo = new SolicitudRepository($db);
        $ahora = 1_700_000_000;
        $vieja = $repo->crear(self::ELLA, 'acceso', 'Texto viejo', 'vieja@example.com', $ahora - 800 * 86400);
        $repo->cerrar($vieja, 'atender', 'Respondida', $ahora - 400 * 86400);
        $reciente = $repo->crear(self::ELLA, 'acceso', 'Texto reciente', 'reciente@example.com', $ahora - 100 * 86400);
        $repo->cerrar($reciente, 'atender', 'Respondida', $ahora - 40 * 86400);
        $pendiente = $repo->crear(self::OTRA, 'supresion', 'Sigue esperando', 'espera@example.com', $ahora - 900 * 86400);

        $this->assertSame([$vieja], $repo->depurar(12, $ahora));
        $this->assertSame([], $repo->depurar(12, $ahora), 'once');

        $v = $repo->ver($vieja);
        $this->assertSame(['', '', null], [$v['contacto'], $v['detalle'], $v['nota']]);
        $this->assertNotNull($v['depurado_en']);
        $this->assertSame(['atendida', 'acceso', self::ELLA], [$v['estado'], $v['derecho'], (int)$v['persona_id']], 'what is not personal stays');
        $this->assertSame('reciente@example.com', $repo->ver($reciente)['contacto'], 'not old enough');
        $this->assertSame('espera@example.com', $repo->ver($pendiente)['contacto'], 'one still waiting is never erased, however old');
        $this->assertSame([$reciente], $repo->depurar(1, $ahora), 'a shorter retention reaches the recent one');
    }

    #[DataProvider('drivers')]
    public function testTheSolicitudsOfASuppressedPersonaStayUntilTheirDataIsErased(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        $repo = new SolicitudRepository($db);
        $n = $repo->crear(self::ELLA, 'supresion', 'x', 'a@example.com');

        // there is no persona with that id at all: persona_id is not a foreign key, so a suppression never finds it in the way
        $this->assertSame(self::ELLA, (int)$repo->ver($n)['persona_id']);
        $this->assertSame(0, (int)$db->fetchValue('SELECT COUNT(*) FROM personas WHERE id = ?', [self::ELLA]));
    }

    #[DataProvider('drivers')]
    public function testThePanelListsEveryPendingOneAndOnlyTheNewestClosedOnesInFull(string $driver): void
    {
        $repo = new SolicitudRepository(TestDatabases::fresh($driver, 'genealogy'));
        $ahora = 1_700_000_000;
        $cerradas = [];
        for ($i = 1; $i <= 4; $i++) {
            $cerradas[$i] = $repo->crear(self::ELLA, 'acceso', "texto {$i}", "c{$i}@example.com", $ahora + $i * 100);
            $repo->cerrar($cerradas[$i], 'atender', "nota {$i}", $ahora + $i * 100 + 5);
        }
        $vieja = $repo->crear(self::OTRA, 'supresion', 'Pendiente muy vieja', 'vieja@example.com', $ahora - 900 * 86400);
        $nueva = $repo->crear(self::OTRA, 'rectificacion', 'Pendiente nueva', 'nueva@example.com', $ahora + 1000);

        $todas = $repo->todas(2);

        $this->assertSame([$nueva, $vieja, $cerradas[4], $cerradas[3]], array_map(static fn(array $s): int => (int)$s['id'], $todas), 'every pending one (newest first), then the 2 newest closed');
        $this->assertSame(['pendiente', 'pendiente', 'atendida', 'atendida'], array_column($todas, 'estado'));
        $this->assertSame('nueva@example.com', $todas[0]['contacto'], 'in full: the owner needs them to answer');
        $this->assertSame('Pendiente nueva', $todas[0]['detalle']);
        $this->assertSame('nota 4', $todas[2]['nota']);
        $this->assertCount(2, array_filter($repo->todas(0), static fn(array $s): bool => $s['estado'] === 'pendiente'), 'with none closed, the pending ones are still all there');
        $this->assertCount(6, $repo->todas());
    }
}
