<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Repositories\Genealogy\RetiroRepository;
use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;

/** bin/pedidos-retiro.php as cron runs it: a real subprocess, a real SQLite file, and its exit code. */
class PedidosRetiroCliTest extends TestCase
{
    private string $archivo;
    private Database $db;
    private RetiroRepository $repo;

    protected function setUp(): void
    {
        $this->archivo = tempnam(sys_get_temp_dir(), 'pedidos_cli_') . '.sqlite';
        $this->db = new Database('sqlite:' . $this->archivo);
        (new Migrator($this->db, dirname(__DIR__, 3) . '/database/genealogy', 'genealogy'))->migrate();
        $this->repo = new RetiroRepository($this->db);
    }

    protected function tearDown(): void
    {
        foreach ([$this->archivo, $this->archivo . '-wal', $this->archivo . '-shm'] as $f) {
            @unlink($f);
        }
        @unlink(substr($this->archivo, 0, -7));
    }

    /** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr */
    private function correr(array $args = [], array $env = []): array
    {
        $proceso = proc_open(
            ['php', dirname(__DIR__, 3) . '/bin/pedidos-retiro.php', ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env + ['PATH' => (string)getenv('PATH'), 'DB_DSN_GENEALOGY' => 'sqlite:' . $this->archivo]
        );
        $this->assertIsResource($proceso);
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proceso), (string)$salida, (string)$error];
    }

    private function pedido(int $horas, string $detalle = 'Es falso.', string $contacto = 'quien@example.com'): int
    {
        $numero = $this->repo->crear('persona', 6128473105, 'me_nombra', $detalle, $contacto);
        $this->db->execute('UPDATE pedidos_retiro SET creado_en = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - $horas * 3600), $numero]);

        return $numero;
    }

    public function testSaysNothingWhenNothingWaits(): void
    {
        [$codigo, $salida, $error] = $this->correr();

        $this->assertSame([0, '', ''], [$codigo, $salida, $error], 'cron mails whatever is printed: silence means nothing to say');
    }

    public function testListsWhatWaitsWithoutWhoAskedNorWhatTheyWrote(): void
    {
        $numero = $this->pedido(5, 'Mi dirección es Calle Falsa 123.', 'secreto@example.com');

        [$codigo, $salida] = $this->correr();

        $this->assertSame(0, $codigo, 'on time: nothing is late');
        $this->assertStringContainsString('1 pedido de retiro espera respuesta (plazo: 24 h)', $salida);
        $this->assertStringContainsString("n.º {$numero}  persona 6128473105  «Habla de mí»  espera hace 5 h", $salida);
        $this->assertStringContainsString('/0/3/', $salida);
        $this->assertStringNotContainsString('secreto@example.com', $salida);
        $this->assertStringNotContainsString('Calle Falsa', $salida);
        $this->assertStringNotContainsString('VENCIDO', $salida);
    }

    public function testALatePedidoMakesTheExitCodeOneAndHidesNothing(): void
    {
        $numero = $this->pedido(30);

        [$codigo, $salida] = $this->correr();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString("n.º {$numero}", $salida);
        $this->assertStringContainsString('espera hace 1 d 6 h  VENCIDO', $salida);
        $this->assertFalse($this->repo->retirado('persona', 6128473105), 'without --ocultar it only tells');
    }

    public function testOcultarHidesTheLateOnesAndLeavesTheOthers(): void
    {
        $tarde = $this->pedido(30);
        $this->db->execute("UPDATE pedidos_retiro SET recurso_id = 6128473101 WHERE id = ?", [$tarde]);
        $a_tiempo = $this->pedido(3);

        [$codigo, $salida] = $this->correr(['--ocultar']);

        $this->assertSame(0, $codigo, 'what was late is now hidden, and nothing late is left');
        $this->assertStringContainsString('2 pedidos de retiro esperan respuesta', $salida);
        $this->assertStringContainsString('VENCIDO: oculto automáticamente', $salida);
        $this->assertTrue($this->repo->retirado('persona', 6128473101));
        $this->assertFalse($this->repo->retirado('persona', 6128473105), 'the one on time is still shown');

        [$segundoCodigo, $segundaSalida] = $this->correr(['--ocultar']);
        $this->assertSame(0, $segundoCodigo);
        $this->assertStringContainsString('1 pedido de retiro espera respuesta', $segundaSalida, 'only the one on time is still waiting');
        $this->assertStringContainsString("n.º {$a_tiempo}", $segundaSalida);
    }

    public function testTheDeadlineComesFromTheFlagOrTheEnvironment(): void
    {
        $this->pedido(5);

        $this->assertSame(0, $this->correr()[0], '5 hours is on time for the default 24');
        $this->assertSame(1, $this->correr(['--horas=4'])[0], 'late for 4');
        $this->assertSame(1, $this->correr([], ['RETIRO_PLAZO_HORAS' => '2'])[0], 'late for the environment\'s 2');
        $this->assertSame(0, $this->correr(['--horas=48'], ['RETIRO_PLAZO_HORAS' => '2'])[0], 'the flag wins');
    }

    public function testRefusesWhatItDoesNotUnderstand(): void
    {
        foreach ([['--horas=0'], ['--horas=abc'], ['--horas=9000'], ['--horas'], ['--ocultar=si'], ['hola']] as $args) {
            [$codigo, $salida, $error] = $this->correr($args);

            $this->assertSame(2, $codigo, implode(' ', $args));
            $this->assertSame('', $salida, implode(' ', $args));
            $this->assertStringContainsString('Uso: php bin/pedidos-retiro.php', $error, implode(' ', $args));
        }
    }

    public function testSaysSoWhenTheDatabaseIsNotMigrated(): void
    {
        $vacia = tempnam(sys_get_temp_dir(), 'pedidos_vacia_') . '.sqlite';
        try {
            [$codigo, $salida, $error] = $this->correr([], ['DB_DSN_GENEALOGY' => 'sqlite:' . $vacia]);
        } finally {
            @unlink($vacia);
            @unlink(substr($vacia, 0, -7));
        }

        $this->assertSame(2, $codigo);
        $this->assertSame('', $salida);
        $this->assertStringContainsString('bin/migrate.php genealogy', $error);
    }
}
