<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Repositories\Genealogy\SolicitudRepository;
use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;
use Tests\Support\ComandoDePrueba;

/** bin/solicitudes.php as the owner and cron run it: a real subprocess, a real SQLite file, its exit code and output. */
class SolicitudesCliTest extends TestCase
{
    use ComandoDePrueba;

    private string $archivo;
    private Database $db;
    private SolicitudRepository $repo;

    protected function setUp(): void
    {
        $this->archivo = tempnam(sys_get_temp_dir(), 'solicitudes_cli_') . '.sqlite';
        $this->db = new Database('sqlite:' . $this->archivo);
        (new Migrator($this->db, dirname(__DIR__, 3) . '/database/genealogy', 'genealogy'))->migrate();
        $this->repo = new SolicitudRepository($this->db);
    }

    protected function tearDown(): void
    {
        foreach ([$this->archivo, $this->archivo . '-wal', $this->archivo . '-shm'] as $f) {
            @unlink($f);
        }
        @unlink(substr($this->archivo, 0, -7));
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function correr(string ...$args): array
    {
        return $this->ejecutarComando('solicitudes', $args, ['DB_DSN_GENEALOGY' => 'sqlite:' . $this->archivo]);
    }

    public function testWithNothingWaitingItSaysNothing(): void
    {
        $this->assertSame([0, '', ''], $this->correr(), 'cron mails whatever is printed');
    }

    public function testTheListingNamesWhatWaitsButNeverWhoAskedNorWhatTheyWrote(): void
    {
        $this->repo->crear(6128479601, 'supresion', 'Mi texto privado', 'ella@example.com', time() - 2 * 86400 - 3600);
        $cerrada = $this->repo->crear(6128479602, 'acceso', 'Otra', 'otra@example.com');
        $this->repo->cerrar($cerrada, 'atender', null);

        [$codigo, $salida, $error] = $this->correr();

        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertStringContainsString('1 solicitud de datos espera respuesta', $salida);
        $this->assertStringContainsString('n.º 1  supresion  persona 6128479601  lleva 2 d 1 h', $salida);
        $this->assertStringNotContainsString('ella@example.com', $salida);
        $this->assertStringNotContainsString('Mi texto privado', $salida);
        $this->assertStringNotContainsString('persona 6128479602', $salida, 'the closed one is not listed');
    }

    public function testVerShowsTheOwnerWhoAskedAndWhatTheyWrote(): void
    {
        $n = $this->repo->crear(6128479601, 'rectificacion', 'Mi apellido está mal escrito', 'ella@example.com');

        [$codigo, $salida, $error] = $this->correr("--ver={$n}");

        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertStringContainsString('Corregir un dato mío sobre la persona 6128479601 (pendiente', $salida);
        $this->assertStringContainsString('Responder a: ella@example.com', $salida);
        $this->assertStringContainsString('Mi apellido está mal escrito', $salida);
    }

    public function testAtenderAndRechazarCloseOnceAndKeepTheNote(): void
    {
        $uno = $this->repo->crear(6128479601, 'acceso', 'x', 'a@example.com');
        $dos = $this->repo->crear(6128479601, 'supresion', 'x', 'b@example.com');

        $this->assertSame([0, "La solicitud {$uno} quedó como «atendida».\n"], array_slice($this->correr("--atender={$uno}", '--nota=Resumen mandado'), 0, 2));
        $this->assertSame([0, "La solicitud {$dos} quedó como «rechazada».\n"], array_slice($this->correr("--rechazar={$dos}", '--nota=No respondió el dato'), 0, 2));

        $this->assertSame(['atendida', 'Resumen mandado'], [$this->repo->ver($uno)['estado'], $this->repo->ver($uno)['nota']]);
        $this->assertSame('rechazada', $this->repo->ver($dos)['estado']);
        [$codigo, , $error] = $this->correr("--atender={$uno}");
        $this->assertSame(1, $codigo, 'closed already');
        $this->assertStringContainsString('ya estaba cerrada', $error);
        $this->assertSame('', $this->correr()[1], 'nothing waits any more');
        $this->assertStringContainsString('Cerrada el', $this->correr("--ver={$uno}")[1]);
        $this->assertStringContainsString('Resumen mandado', $this->correr("--ver={$uno}")[1]);
    }

    public function testASolicitudThatDoesNotExistExitsWithOne(): void
    {
        foreach (['--ver=99', '--atender=99', '--rechazar=99'] as $opcion) {
            [$codigo, $salida, $error] = $this->correr($opcion);
            $this->assertSame([1, ''], [$codigo, $salida], $opcion);
            $this->assertNotSame('', $error, $opcion);
        }
    }

    public function testDepurarErasesOldClosedOnesAndPrintsOnlyACount(): void
    {
        $vieja = $this->repo->crear(6128479601, 'acceso', 'Texto', 'vieja@example.com', time() - 800 * 86400);
        $this->repo->cerrar($vieja, 'atender', 'Nota', time() - 500 * 86400);
        $pendiente = $this->repo->crear(6128479601, 'acceso', 'Espera', 'espera@example.com', time() - 800 * 86400);

        $this->assertSame([0, '', ''], $this->correr('--depurar', '--meses=240'), 'a retention that reaches nothing says nothing');
        [$codigo, $salida, $error] = $this->correr('--depurar');

        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertSame("Se borraron el correo y el texto de 1 solicitud cerrada hace más de 12 meses.\n", $salida);
        $this->assertSame('', $this->repo->ver($vieja)['contacto']);
        $this->assertSame('espera@example.com', $this->repo->ver($pendiente)['contacto'], 'one that waits is never erased');
        $this->assertSame('', $this->correr('--depurar')[1], 'silent when there is nothing to erase');
    }

    public function testUsageErrorsExitWithTwoAndChangeNothing(): void
    {
        $n = $this->repo->crear(6128479601, 'acceso', 'x', 'a@example.com');

        foreach ([['--raro'], ['--ver=x'], ['--ver=1', '--atender=1'], ['--nota=algo'], ['--ver=1', '--nota=algo'], ["--atender={$n}", '--meses=0'], ['--depurar', '--meses=241'], ['suelto']] as $args) {
            $this->assertSame(2, $this->correr(...$args)[0], implode(' ', $args));
        }
        $this->assertSame('pendiente', $this->repo->ver($n)['estado']);
    }

    public function testDepurarAlsoErasesTheDeliveriesThatExpiredLongAgoAndSaysSoOnlyWhenThereWere(): void
    {
        $entregas = new \App\Repositories\Genealogy\EntregaRepository($this->db);
        $this->db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (6128479601, 'Rosa', 'Prueba')");
        $entregas->crear(6128479601, 2, time() - 800 * 86400);
        $entregas->crear(6128479601, 2, time() - 800 * 86400);
        $viva = $entregas->crear(6128479601, 30);

        [$codigo, $salida, $error] = $this->correr('--depurar');

        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertSame("Se borraron 2 entregas con clave vencidas hace más de 12 meses.\n", $salida);
        $this->assertSame(1, (int)$this->db->fetchValue('SELECT COUNT(*) FROM entregas_persona'));
        $this->assertSame(6128479601, $entregas->abrir($viva['token'], $viva['clave']), 'the one that works still works');
        $this->assertSame([0, '', ''], $this->correr('--depurar'), 'nothing left to erase: nothing said');
    }
}
