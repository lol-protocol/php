<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;

/** bin/fusionar.php as the owner runs it: a real subprocess, a real SQLite file, and its exit code. */
class FusionarCliTest extends TestCase
{
    private string $archivo;
    private Database $db;

    protected function setUp(): void
    {
        $this->archivo = tempnam(sys_get_temp_dir(), 'fusionar_cli_') . '.sqlite';
        $this->db = new Database('sqlite:' . $this->archivo);
        (new Migrator($this->db, dirname(__DIR__, 3) . '/database/genealogy', 'genealogy'))->migrate();

        $this->db->execute("INSERT INTO grupos (id, apellido, origen) VALUES (582317, 'García', 'es'), (582399, 'Garcia', NULL)");
        $this->db->execute("INSERT INTO personas (id, nombres, apellidos, grupo_id) VALUES (6128479701, 'Ana', 'García', 582399), (6128479702, 'Luis', 'García', 582317), (6128479703, 'Ana María', 'García', NULL)");
    }

    protected function tearDown(): void
    {
        foreach ([$this->archivo, $this->archivo . '-wal', $this->archivo . '-shm'] as $f) {
            @unlink($f);
        }
        @unlink(substr($this->archivo, 0, -7));
    }

    /** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr */
    private function correr(string ...$args): array
    {
        $proceso = proc_open(
            [PHP_BINARY, dirname(__DIR__, 3) . '/bin/fusionar.php', ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PATH' => (string)getenv('PATH'), 'DB_DSN_GENEALOGY' => 'sqlite:' . $this->archivo]
        );
        $this->assertIsResource($proceso);
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proceso), (string)$salida, (string)$error];
    }

    public function testGrupoWithoutConfirmarOnlyShowsWhatWouldMove(): void
    {
        [$codigo, $salida] = $this->correr('grupo', '582399', '582317');

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('Duplicado (desaparece): 582399 Garcia', $salida);
        $this->assertStringContainsString('Queda: 582317 García', $salida);
        $this->assertStringContainsString('personas: 1', $salida);
        $this->assertStringContainsString('Nada cambió', $salida);
        $this->assertSame(1, (int)$this->db->fetchValue('SELECT COUNT(*) FROM grupos WHERE id = 582399'));
    }

    public function testGrupoConfirmarMergesAndLeavesTheAlias(): void
    {
        [$codigo, $salida] = $this->correr('grupo', '582399', '582317', '--confirmar');

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('/582399/ redirige ahora a /582317/', $salida);
        $this->assertSame(0, (int)$this->db->fetchValue('SELECT COUNT(*) FROM grupos WHERE id = 582399'));
        $this->assertSame(582317, (int)$this->db->fetchValue('SELECT grupo_id FROM personas WHERE id = 6128479701'));
        $this->assertSame(582317, (int)$this->db->fetchValue('SELECT grupo_id FROM grupo_alias WHERE id = 582399'));
    }

    public function testTheConfirmationCanComeFirst(): void
    {
        [$codigo] = $this->correr('--confirmar', 'grupo', '582399', '582317');

        $this->assertSame(0, $codigo);
        $this->assertSame(1, (int)$this->db->fetchValue('SELECT COUNT(*) FROM grupo_alias'));
    }

    public function testAGrupoThatDoesNotExistIsAnError(): void
    {
        [$codigo, , $error] = $this->correr('grupo', '582399', '111111');

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('No existe el grupo 111111', $error);
    }

    public function testMergingAGrupoIntoItselfIsRefused(): void
    {
        [$codigo, , $error] = $this->correr('grupo', '582317', '582317', '--confirmar');

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('consigo mismo', $error);
    }

    /** The old form, without a type, is still a persona merge. */
    public function testWithoutATypeItStillMergesPersonas(): void
    {
        [$codigo, $salida] = $this->correr('6128479703', '6128479701', '--confirmar');

        $this->assertSame(0, $codigo, $salida);
        $this->assertSame(0, (int)$this->db->fetchValue('SELECT COUNT(*) FROM personas WHERE id = 6128479703'));
        $this->assertSame(6128479701, (int)$this->db->fetchValue('SELECT persona_id FROM persona_alias WHERE id = 6128479703'));
    }

    public function testBadUsageExitsWithTwo(): void
    {
        foreach ([[], ['grupo'], ['grupo', '582399'], ['abc', '1'], ['grupo', '1', '2', '3']] as $args) {
            [$codigo, , $error] = $this->correr(...$args);

            $this->assertSame(2, $codigo, implode(' ', $args));
            $this->assertStringContainsString('Uso:', $error);
        }
    }
}
