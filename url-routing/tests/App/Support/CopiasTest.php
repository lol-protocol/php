<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Copias;
use App\Support\Database;
use PHPUnit\Framework\TestCase;

/**
 * Copias (bin/copias.php): the daily copy of a database, checked before it takes its name, and the rotation by the
 * date in the name. SQLite is tested for real; PostgreSQL with the real pg_dump when the tests are given a database
 * (TEST_PG_DSN, as the rest of the suite) and the tools are on the PATH.
 */
class CopiasTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/copias_test_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/origen', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,*/}{*,.parcial}', GLOB_BRACE) ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        foreach (['/copias', '/origen', ''] as $sub) {
            @rmdir($this->dir . $sub);
        }
    }

    /** A SQLite database with one persona, still open (so its WAL has not been folded into the file). */
    private function baseSqlite(): array
    {
        $ruta = $this->dir . '/origen/g.sqlite';
        $db = new Database('sqlite:' . $ruta);
        $db->execute('CREATE TABLE personas (id INTEGER PRIMARY KEY, nombres TEXT)');
        $db->execute("INSERT INTO personas (id, nombres) VALUES (1, 'Rosa Elena')");

        return [$db, $ruta];
    }

    public function testACopyOfSqliteHoldsWhatIsInTheWalAndIsNamedAfterToday(): void
    {
        [$db, $ruta] = $this->baseSqlite();
        $copias = new Copias($this->dir . '/copias');

        $archivo = $copias->respaldar('genealogy', 'sqlite:' . $ruta);

        $this->assertSame($this->dir . '/copias/genealogy-' . date('Y-m-d') . '.sqlite', $archivo);
        $copia = new Database('sqlite:' . $archivo);
        $this->assertSame('Rosa Elena', $copia->fetchValue('SELECT nombres FROM personas WHERE id = 1'), 'the row was still in the WAL of the source');
        $this->assertSame([], glob($this->dir . '/copias/*.parcial'), 'no half-written file is left');
        unset($db);
    }

    public function testACopyIsReadableByItsOwnerOnly(): void
    {
        [$db, $ruta] = $this->baseSqlite();

        $archivo = (new Copias($this->dir . '/copias'))->respaldar('genealogy', 'sqlite:' . $ruta);

        $this->assertSame('0600', substr(sprintf('%o', fileperms($archivo)), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->dir . '/copias')), -4), 'and the folder it created too');
        unset($db);
    }

    public function testTodaysCopyIsReplacedWhenTheJobRunsTwice(): void
    {
        [$db, $ruta] = $this->baseSqlite();
        $copias = new Copias($this->dir . '/copias');
        $copias->respaldar('genealogy', 'sqlite:' . $ruta);
        $db->execute("INSERT INTO personas (id, nombres) VALUES (2, 'Otra')");

        $archivo = $copias->respaldar('genealogy', 'sqlite:' . $ruta);

        $this->assertSame(2, (int)(new Database('sqlite:' . $archivo))->fetchValue('SELECT COUNT(*) FROM personas'));
        unset($db);
    }

    public function testAMissingDatabaseFailsAndLeavesNothing(): void
    {
        $copias = new Copias($this->dir . '/copias');

        try {
            $copias->respaldar('genealogy', 'sqlite:' . $this->dir . '/origen/no-existe.sqlite');
            $this->fail('there is nothing to copy');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No existe la base SQLite', $e->getMessage());
        }

        $this->assertSame([], glob($this->dir . '/copias/*'));
    }

    public function testADatabaseWithoutTablesDoesNotPassTheCheck(): void
    {
        $vacia = $this->dir . '/origen/vacia.sqlite';
        (new \PDO('sqlite:' . $vacia))->exec('PRAGMA user_version = 1');
        $copias = new Copias($this->dir . '/copias');

        try {
            $copias->respaldar('pos', 'sqlite:' . $vacia);
            $this->fail('an empty copy is not a copy');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no pasó la comprobación', $e->getMessage());
        }

        $this->assertSame([], glob($this->dir . '/copias/*'), 'neither the copy nor its partial file stays');
    }

    public function testAnUnknownEngineAndABadSiteNameAreRefused(): void
    {
        $copias = new Copias($this->dir . '/copias');

        try {
            $copias->respaldar('genealogy', 'mysql:host=x;dbname=y');
            $this->fail('only sqlite and pgsql');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('solo sqlite: y pgsql:', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $copias->respaldar('../fuera', 'sqlite::memory:');
    }

    public function testTheRetentionMustBeBetweenOneDayAndTenYears(): void
    {
        foreach ([0, -1, 3651] as $dias) {
            try {
                new Copias($this->dir, $dias);
                $this->fail("{$dias} days");
            } catch (\InvalidArgumentException) {
            }
        }
        $this->assertInstanceOf(Copias::class, new Copias($this->dir, 3650));
    }

    public function testRotatingDeletesOnlyTheOldCopiesOfThatSite(): void
    {
        $copias = new Copias($this->dir . '/copias', 30);
        mkdir($this->dir . '/copias');
        $dia = static fn(int $atras): string => date('Y-m-d', strtotime("-{$atras} days"));
        $archivos = [
            "genealogy-{$dia(31)}.dump" => true,   // one day too old: goes
            "genealogy-{$dia(400)}.sqlite" => true,
            "genealogy-{$dia(30)}.dump" => false,  // exactly the retention: stays
            'genealogy-' . date('Y-m-d') . '.dump' => false,
            "pos-{$dia(90)}.sqlite" => false,      // another site: not this call's business
            "genealogy-{$dia(90)}.txt" => false,   // not a copy
            "notas-{$dia(90)}" => false,
            'genealogy-ayer.dump' => false,
        ];
        foreach (array_keys($archivos) as $nombre) {
            file_put_contents($this->dir . '/copias/' . $nombre, 'x');
        }

        $borrados = $copias->rotar('genealogy');

        sort($borrados);
        $esperados = array_keys(array_filter($archivos));
        sort($esperados);
        $this->assertSame($esperados, $borrados);
        foreach ($archivos as $nombre => $seBorra) {
            $this->assertSame(!$seBorra, file_exists($this->dir . '/copias/' . $nombre), $nombre);
        }
        array_map('unlink', glob($this->dir . '/copias/*') ?: []);
    }

    public function testTheSendCommandGetsTheQuotedPath(): void
    {
        $copias = new Copias($this->dir . '/copias');
        mkdir($this->dir . '/copias');
        $origen = $this->dir . "/copias/con espacio y 'comilla'.sqlite";
        file_put_contents($origen, 'contenido');
        $destino = $this->dir . '/copias/enviado';

        $copias->enviar('cp {archivo} ' . escapeshellarg($destino), $origen);

        $this->assertSame('contenido', file_get_contents($destino));
        unlink($origen);
        unlink($destino);
    }

    public function testAFailingSendCommandIsAnErrorAndOneWithoutThePlaceholderIsRefused(): void
    {
        $copias = new Copias($this->dir . '/copias');

        try {
            $copias->enviar('sh -c "echo no hay red >&2; exit 3" {archivo}', $this->dir . '/x');
            $this->fail('the command failed');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('falló', $e->getMessage());
            $this->assertStringContainsString('no hay red', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $copias->enviar('true', $this->dir . '/x');
    }

    // --- PostgreSQL, with the real pg_dump ------------------------------------------------------

    private function postgres(): array
    {
        $dsn = (string)getenv('TEST_PG_DSN');
        if ($dsn === '' || trim((string)shell_exec('command -v pg_dump pg_restore 2>/dev/null')) === '') {
            $this->markTestSkipped('needs TEST_PG_DSN and pg_dump/pg_restore on the PATH');
        }

        return [$dsn, (string)getenv('TEST_PG_USER'), (string)getenv('TEST_PG_PASSWORD')];
    }

    public function testACopyOfPostgresIsADumpThatPgRestoreCanRead(): void
    {
        [$dsn, $usuario, $clave] = $this->postgres();

        $archivo = (new Copias($this->dir . '/copias'))->respaldar('genealogy', $dsn, $usuario, $clave);

        $this->assertSame($this->dir . '/copias/genealogy-' . date('Y-m-d') . '.dump', $archivo);
        $this->assertGreaterThan(0, filesize($archivo));
        $this->assertSame('0600', substr(sprintf('%o', fileperms($archivo)), -4));
        $this->assertSame([], glob($this->dir . '/copias/*.parcial'));
        unlink($archivo);
    }

    public function testAFailingPgDumpLeavesNothingAndSaysWhy(): void
    {
        [$dsn, $usuario, $clave] = $this->postgres();
        $copias = new Copias($this->dir . '/copias');

        try {
            $copias->respaldar('genealogy', $dsn . ';dbname=no_existe_esta_base', $usuario, $clave);
            $this->fail('the database does not exist');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pg_dump falló', $e->getMessage());
        }

        $this->assertSame([], glob($this->dir . '/copias/*'));
        $this->expectException(\RuntimeException::class);
        $copias->respaldar('genealogy', 'pgsql:host=127.0.0.1');
    }

    // --- encrypted, with the real age -----------------------------------------------------------

    /** @return array{string, string} [public key, path of the file holding the private one]; skips the test without age */
    private function clavesAge(): array
    {
        if (trim((string)shell_exec('command -v age age-keygen 2>/dev/null')) === '') {
            $this->markTestSkipped('needs age and age-keygen on the PATH');
        }
        @mkdir($this->dir . '/origen', 0777, true);
        $privada = $this->dir . '/origen/clave.txt';
        shell_exec('age-keygen -o ' . escapeshellarg($privada) . ' 2>/dev/null');
        preg_match('/age1[a-z0-9]{58}/', (string)file_get_contents($privada), $m);
        $this->assertNotEmpty($m);

        return [$m[0], $privada];
    }

    public function testAnEncryptedCopyOpensWithThePrivateKeyAndLeavesNothingPlain(): void
    {
        [$publica, $privada] = $this->clavesAge();
        [$db, $ruta] = $this->baseSqlite();
        $copias = new Copias($this->dir . '/copias', 30, [$publica]);

        $archivo = $copias->respaldar('genealogy', 'sqlite:' . $ruta);

        $this->assertSame($this->dir . '/copias/genealogy-' . date('Y-m-d') . '.sqlite.age', $archivo);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($archivo)), -4));
        $this->assertSame('age-encryption.org/v1', trim((string)file_get_contents($archivo, false, null, 0, 21)));
        $this->assertSame([$archivo], glob($this->dir . '/copias/*'), 'only the encrypted file: no plain copy and no partial');
        $this->assertStringNotContainsString('Rosa Elena', (string)file_get_contents($archivo), 'nothing readable inside');

        $abierta = $this->dir . '/origen/abierta.sqlite';
        shell_exec('age -d -i ' . escapeshellarg($privada) . ' -o ' . escapeshellarg($abierta) . ' ' . escapeshellarg($archivo) . ' 2>/dev/null');
        $this->assertSame('Rosa Elena', (new Database('sqlite:' . $abierta))->fetchValue('SELECT nombres FROM personas WHERE id = 1'), 'and the private key gets it back');
        unset($db);
    }

    public function testItEncryptsForSeveralRecipients(): void
    {
        [$una] = $this->clavesAge();
        shell_exec('age-keygen -o ' . escapeshellarg($this->dir . '/origen/otra.txt') . ' 2>/dev/null');
        preg_match('/age1[a-z0-9]{58}/', (string)file_get_contents($this->dir . '/origen/otra.txt'), $m);
        [$db, $ruta] = $this->baseSqlite();

        $archivo = (new Copias($this->dir . '/copias', 30, [$una, $m[0]]))->respaldar('genealogy', 'sqlite:' . $ruta);

        foreach (['clave.txt', 'otra.txt'] as $clave) {
            $abierta = $this->dir . '/origen/abierta-' . $clave;
            shell_exec('age -d -i ' . escapeshellarg($this->dir . '/origen/' . $clave) . ' -o ' . escapeshellarg($abierta) . ' ' . escapeshellarg($archivo) . ' 2>/dev/null');
            $this->assertSame('Rosa Elena', (new Database('sqlite:' . $abierta))->fetchValue('SELECT nombres FROM personas WHERE id = 1'), $clave);
            unlink($abierta);
        }
        unset($db);
    }

    public function testWithoutAgeTheRunFailsAndLeavesNoCopyAtAll(): void
    {
        [$publica] = $this->clavesAge();
        [$db, $ruta] = $this->baseSqlite();
        $copias = new Copias($this->dir . '/copias', 30, [$publica]);
        $path = (string)getenv('PATH');

        putenv('PATH=/nonexistent');
        try {
            $copias->respaldar('genealogy', 'sqlite:' . $ruta);
            $this->fail('it must not fall back to a plain copy');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('age', $e->getMessage());
        } finally {
            putenv('PATH=' . $path);
        }

        $this->assertSame([], glob($this->dir . '/copias/*'), 'not even a plain copy, nor a partial one');
        unset($db);
    }

    public function testARecipientThatIsNotAnAgeKeyIsRefused(): void
    {
        foreach (['', 'nope', 'age1corta', 'age1' . str_repeat('b', 58), 'AGE1' . str_repeat('q', 58), 'age1' . str_repeat('q', 59)] as $malo) {
            try {
                new Copias($this->dir, 30, [$malo]);
                $this->fail("«{$malo}»");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('age1', $e->getMessage());
            }
        }
        $this->assertInstanceOf(Copias::class, new Copias($this->dir, 30, ['age1' . str_repeat('q', 58)]));
    }

    public function testRotationAlsoAgesOutTheEncryptedCopiesAndASweepRemovesWhatAKilledRunLeft(): void
    {
        mkdir($this->dir . '/copias');
        $dia = static fn(int $atras): string => date('Y-m-d', strtotime("-{$atras} days"));
        foreach (["genealogy-{$dia(40)}.dump.age", "genealogy-{$dia(40)}.sqlite.age", "genealogy-{$dia(5)}.dump.age", "pos-{$dia(40)}.sqlite.age"] as $nombre) {
            file_put_contents($this->dir . '/copias/' . $nombre, 'x');
        }
        $this->assertSame(["genealogy-{$dia(40)}.dump.age", "genealogy-{$dia(40)}.sqlite.age"], (function () {
            $b = (new Copias($this->dir . '/copias', 30))->rotar('genealogy');
            sort($b);

            return $b;
        })());
        $this->assertFileExists($this->dir . '/copias/genealogy-' . $dia(5) . '.dump.age');
        $this->assertFileExists($this->dir . '/copias/pos-' . $dia(40) . '.sqlite.age', 'another site');

        // what a run killed half way left: it is swept at the start of the next one, and only for that site
        $sobras = ['genealogy-' . date('Y-m-d') . '.sqlite.sin-cifrar.parcial', 'genealogy-' . date('Y-m-d') . '.sqlite.parcial', 'pos-' . date('Y-m-d') . '.sqlite.parcial'];
        foreach ($sobras as $s) {
            file_put_contents($this->dir . '/copias/' . $s, 'a medias');
        }
        [$db, $ruta] = $this->baseSqlite();
        (new Copias($this->dir . '/copias'))->respaldar('genealogy', 'sqlite:' . $ruta);
        $this->assertFileDoesNotExist($this->dir . '/copias/' . $sobras[0]);
        $this->assertFileDoesNotExist($this->dir . '/copias/' . $sobras[1]);
        $this->assertFileExists($this->dir . '/copias/' . $sobras[2], 'the leftovers of another site are not touched');
        unlink($this->dir . '/copias/' . $sobras[2]);
        unset($db);
    }
}
