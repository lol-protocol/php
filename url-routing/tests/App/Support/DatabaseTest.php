<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

class DatabaseTest extends TestCase
{
    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    private function db(): Database
    {
        return new Database('sqlite::memory:');
    }

    public function testSiguienteNumeroEmpiezaEnUnoYSigueAlMayorDeSuGrupo(): void
    {
        $db = $this->db();
        $db->execute('CREATE TABLE t (grupo INTEGER NOT NULL, n INTEGER NOT NULL)');

        $this->assertSame(1, $db->siguienteNumero('t', 'n'));
        $db->execute('INSERT INTO t (grupo, n) VALUES (1, 1), (1, 2), (2, 7)');
        $this->assertSame(8, $db->siguienteNumero('t', 'n'));
        $this->assertSame(3, $db->siguienteNumero('t', 'n', ['grupo' => 1]));
        $this->assertSame(1, $db->siguienteNumero('t', 'n', ['grupo' => 9]));
    }

    public function testSiguienteNumeroRechazaUnNombreInyectado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->siguienteNumero('t; DROP TABLE t', 'n');
    }

    public function testSoloUnChoqueDeUnicidadSeReintenta(): void
    {
        $db = $this->db();
        $db->execute('CREATE TABLE u (id INTEGER PRIMARY KEY, n INTEGER NOT NULL CHECK (n > 0))');
        $db->execute('INSERT INTO u (id, n) VALUES (1, 1)');

        // Pierde la carrera por el 1 la primera vez; la segunda lee el máximo que dejó el ganador.
        $intentos = 0;
        $id = $db->transaccionConReintento(function (Database $db) use (&$intentos): int {
            $intentos++;
            $id = $intentos === 1 ? 1 : $db->siguienteNumero('u', 'id');
            $db->execute('INSERT INTO u (id, n) VALUES (?, 1)', [$id]);

            return $id;
        });
        $this->assertSame([2, 2], [$id, $intentos]);

        // Un CHECK que rechaza el dato falla igual cada vez: se lanza al primer intento.
        $intentos = 0;
        try {
            $db->transaccionConReintento(function (Database $db) use (&$intentos): void {
                $intentos++;
                $db->execute('INSERT INTO u (id, n) VALUES (9, 0)');
            });
            $this->fail('El CHECK debió fallar');
        } catch (\PDOException $e) {
            $this->assertSame(1, $intentos);
            $this->assertFalse(Database::esChoqueDeUnicidad($e));
        }

        // Y si el choque no se va, tras los intentos se lanza.
        $intentos = 0;
        try {
            $db->transaccionConReintento(function (Database $db) use (&$intentos): void {
                $intentos++;
                $db->execute('INSERT INTO u (id, n) VALUES (1, 1)');
            }, 2);
            $this->fail('El choque debió lanzarse');
        } catch (\PDOException $e) {
            $this->assertSame(2, $intentos);
            $this->assertTrue(Database::esChoqueDeUnicidad($e));
        }
    }

    public function testInsertRejectsInjectedColumnName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->insert('personas', ['nombre) VALUES (1); DROP TABLE personas; --' => 'x']);
    }

    public function testInsertRejectsInjectedTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->insert('personas; DROP TABLE personas', ['nombre' => 'x']);
    }

    public function testUpdateRejectsInjectedWhereColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->update('personas', ['nombre' => 'x'], ['1=1 OR id' => 1]);
    }

    public function testDeleteRejectsInjectedWhereColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->delete('personas', ['id = id OR 1' => 1]);
    }

    public function testListArrayKeysAreNotValidColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->insert('personas', ['x', 'y']);
    }

    public function testDeleteWithoutWhereIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->delete('personas', []);
    }

    #[DataProvider('drivers')]
    public function testCrudWithPlainIdentifiers(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'pos');

        $db->insert('usuarios', ['id' => 50, 'email' => 'x@example.com', 'nombre' => 'Equis']);
        $this->assertSame(1, $db->update('usuarios', ['nombre' => 'Ye'], ['id' => 50]));
        $this->assertSame('Ye', $db->fetchValue('SELECT nombre FROM usuarios WHERE id = ?', [50]));
        $this->assertSame(1, $db->delete('usuarios', ['id' => 50]));
        $this->assertNull($db->fetchOne('SELECT * FROM usuarios WHERE id = ?', [50]));
    }

    /**
     * PostgreSQL's JIT compiled the visitor's searches on every request (2.5 s of a 70 ms query with 20,000
     * personas), whatever the server's own setting says; the connection turns it off.
     */
    #[DataProvider('drivers')]
    public function testPostgresConnectionsHaveJitTurnedOff(string $driver): void
    {
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('JIT is a PostgreSQL setting.');
        }
        $db = TestDatabases::fresh($driver, 'pos');

        $this->assertSame('off', $db->fetchValue('SHOW jit'));
    }

    #[DataProvider('drivers')]
    public function testTransactionRollsBackOnError(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'pos');

        try {
            $db->transaction(function (Database $tx): void {
                $tx->insert('usuarios', ['id' => 60, 'email' => 'a@example.com', 'nombre' => 'A']);
                $tx->insert('usuarios', ['id' => 61, 'email' => 'a@example.com', 'nombre' => 'B']); // duplicate email
            });
            $this->fail('Unique violation should propagate');
        } catch (\PDOException) {
        }

        $this->assertNull($db->fetchOne('SELECT id FROM usuarios WHERE id = ?', [60]));
    }

    #[DataProvider('drivers')]
    public function testQueryErrorsPropagateInsteadOfLookingLikeNoRows(string $driver): void
    {
        $this->expectException(\PDOException::class);

        TestDatabases::fresh($driver, 'pos')->fetchAll('SELECT * FROM no_such_table');
    }

    public function testSqliteEnforcesForeignKeys(): void
    {
        $db = TestDatabases::fresh('sqlite', 'pos');

        $this->expectException(\PDOException::class);
        $db->insert('deseos', ['usuario_id' => 999, 'producto_id' => 999]);
    }

    public function testForSiteFallsBackToALocalSqliteFile(): void
    {
        putenv('DB_DSN_TESTSITE');
        putenv('DB_DSN');

        $db = Database::forSite('testsite');
        $ref = new \ReflectionProperty(Database::class, 'dsn');

        $this->assertStringEndsWith('/var/testsite.sqlite', $ref->getValue($db));
    }

    public function testForSiteUsesOnlyTheSiteSpecificVariable(): void
    {
        putenv('DB_DSN=sqlite:/tmp/compartida.sqlite');
        putenv('DB_DSN_TESTSITE=sqlite:/tmp/especifica.sqlite');

        try {
            $ref = new \ReflectionProperty(Database::class, 'dsn');
            $this->assertSame('sqlite:/tmp/especifica.sqlite', $ref->getValue(Database::forSite('testsite')));
            // A shared DB_DSN is ignored: two sites in one database would mix
            // their same-named tables.
            $this->assertStringEndsWith('/var/otro.sqlite', $ref->getValue(Database::forSite('otro')));
        } finally {
            putenv('DB_DSN');
            putenv('DB_DSN_TESTSITE');
        }
    }

    // --- nested transactions ------------------------------------------------------------------------

    private function grupos(Database $db): array
    {
        return array_map('intval', array_column($db->fetchAll('SELECT id FROM grupos ORDER BY id'), 'id'));
    }

    private function grupo(Database $db, int $id): void
    {
        $db->execute('INSERT INTO grupos (id, apellido) VALUES (?, ?)', [$id, "Apellido {$id}"]);
    }

    #[DataProvider('drivers')]
    public function testUnaTransaccionDentroDeOtraSeConfirmaConLaExterna(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');

        $devuelto = $db->transaction(function (Database $db): string {
            $this->grupo($db, 100001);
            $interno = $db->transaction(function (Database $db): string {
                $this->grupo($db, 100002);

                return 'interno';
            });
            $this->grupo($db, 100003);

            return "externo+{$interno}";
        });

        $this->assertSame('externo+interno', $devuelto);
        $this->assertSame([100001, 100002, 100003], $this->grupos($db), $driver);
        $this->assertFalse($db->pdo()->inTransaction(), 'the outer one committed');
    }

    #[DataProvider('drivers')]
    public function testSiLaInternaFallaLaExternaPuedeSeguir(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');

        $db->transaction(function (Database $db): void {
            $this->grupo($db, 100001);
            try {
                $db->transaction(function (Database $db): void {
                    $this->grupo($db, 100002);
                    throw new \RuntimeException('falla la interna');
                });
                $this->fail('the inner exception must reach the outer work');
            } catch (\RuntimeException $e) {
                $this->assertSame('falla la interna', $e->getMessage());
            }
            $this->grupo($db, 100003);
        });

        $this->assertSame([100001, 100003], $this->grupos($db), "{$driver}: only the inner one's rows are undone");
    }

    #[DataProvider('drivers')]
    public function testSiLaInternaFallaYNadieLaAtrapaTodoSeDeshaceYLlegaElErrorOriginal(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');

        try {
            $db->transaction(function (Database $db): void {
                $this->grupo($db, 100001);
                $db->transaction(function (Database $db): void {
                    $this->grupo($db, 100002);
                    throw new \DomainException('original');
                });
            });
            $this->fail('the exception must reach the caller');
        } catch (\DomainException $e) {
            $this->assertSame('original', $e->getMessage());
        }

        $this->assertSame([], $this->grupos($db), $driver);
        $this->assertFalse($db->pdo()->inTransaction());
    }

    #[DataProvider('drivers')]
    public function testSiLaExternaFallaDespuesDeLaInternaTambienSeDeshaceLaInterna(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');

        try {
            $db->transaction(function (Database $db): void {
                $db->transaction(fn(Database $db) => $this->grupo($db, 100001));
                throw new \LogicException('falla la externa');
            });
            $this->fail('the exception must reach the caller');
        } catch (\LogicException) {
        }

        $this->assertSame([], $this->grupos($db), $driver);
    }

    #[DataProvider('drivers')]
    public function testUnaTransaccionAbiertaEnElPDOTambienLaAdmite(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        $pdo = $db->pdo();

        $pdo->beginTransaction();
        $this->grupo($db, 100001);
        $db->transaction(fn(Database $db) => $this->grupo($db, 100002));
        try {
            $db->transaction(function (Database $db): void {
                $this->grupo($db, 100003);
                throw new \RuntimeException('x');
            });
        } catch (\RuntimeException) {
        }
        $this->assertTrue($pdo->inTransaction(), 'the one the caller opened is still theirs to end');
        $pdo->commit();

        $this->assertSame([100001, 100002], $this->grupos($db), $driver);
    }

    #[DataProvider('drivers')]
    public function testSiElTrabajoYaTerminoLaTransaccionElErrorNoSeTapa(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');

        // $work that ends the transaction itself and then fails: rollBack() would throw "no active
        // transaction" and bury the real error.
        try {
            $db->transaction(function (Database $db): void {
                $db->pdo()->rollBack();
                throw new \RuntimeException('el error de verdad');
            });
            $this->fail('the exception must reach the caller');
        } catch (\RuntimeException $e) {
            $this->assertSame('el error de verdad', $e->getMessage(), $driver);
        }
    }

    #[DataProvider('drivers')]
    public function testDespuesDeUnFalloLasTransaccionesSiguenFuncionando(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');

        foreach ([1, 2, 3] as $vuelta) {
            try {
                $db->transaction(function (Database $db): void {
                    $db->transaction(function (): void {
                        throw new \RuntimeException('x');
                    });
                });
            } catch (\RuntimeException) {
            }
        }
        $db->transaction(fn(Database $db) => $db->transaction(fn(Database $db) => $this->grupo($db, 100001)));

        $this->assertSame([100001], $this->grupos($db), "{$driver}: the depth went back to zero each time");
    }

    // --- TRANSLATE() and octet_length() on SQLite ---------------------------------------------------

    public function testTraducirCambiaCadaCaracterPorSuPareja(): void
    {
        $this->assertSame('garcia nunez', Database::traducir('garcía nuñez', 'íñ', 'in'));
        $this->assertSame('aberg', Database::traducir('åberg', 'å', 'a'));
        $this->assertNull(Database::traducir(null, 'á', 'a'));
        $this->assertSame('sin cambios', Database::traducir('sin cambios', 'á', 'a'));
    }

    public function testTraducirBorraLosCaracteresSinParejaComoPostgreSQL(): void
    {
        // $de is longer than $a: the extra characters are deleted
        $this->assertSame('garcia', Database::traducir("garci\u{0301}a", "í\u{0301}", 'i'));
        $this->assertSame('ab', Database::traducir('a-b-', '-', ''));
    }

    public function testTraducirConUnCaracterRepetidoEnDeGanaElPrimero(): void
    {
        $this->assertSame('x', Database::traducir('a', 'aa', 'xy'));
    }

    public function testTraducirNoMezclaLosMapasDeDosLlamadasSeguidas(): void
    {
        // The map is kept between calls; a call with other strings must not use the previous one
        $this->assertSame('b', Database::traducir('a', 'a', 'b'));
        $this->assertSame('c', Database::traducir('a', 'a', 'c'));
        $this->assertSame('a', Database::traducir('a', 'x', 'y'));
        $this->assertSame('b', Database::traducir('a', 'a', 'b'));
        $this->assertSame('abc', Database::traducir('abc', '', ''));
    }

    public function testElCatalogoVieneConUnTranslateYUnOctetLengthEnSQLite(): void
    {
        $db = $this->db();

        $this->assertSame('garcia', $db->fetchValue("SELECT translate('garcía', 'í', 'i')"));
        $this->assertSame(2, (int)$db->fetchValue("SELECT octet_length('é')"));
        $this->assertSame(1, (int)$db->fetchValue("SELECT length('é')"));
        $this->assertNull($db->fetchValue('SELECT octet_length(NULL)'));
    }

    public function testBytesCuentaBytesNoCaracteres(): void
    {
        $this->assertSame(0, Database::bytes(''));
        $this->assertSame(3, Database::bytes('abc'));
        $this->assertSame(2, Database::bytes('é'));
        $this->assertSame(3, Database::bytes('ł '));
        $this->assertNull(Database::bytes(null));
    }

    /**
     * transaccionConReintento() asks again when it loses a race for a unique value, and it is called from
     * inside other transactions (OrdenRepository::crear() from a caller that already opened one): the retry
     * has to undo only its own attempt, which on PostgreSQL means getting out of the aborted state with
     * ROLLBACK TO SAVEPOINT. Before transaction() could be nested, this threw "already an active transaction".
     */
    #[DataProvider('drivers')]
    public function testElReintentoFuncionaDentroDeUnaTransaccionExterna(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        $this->grupo($db, 100001);
        $intentos = 0;

        $db->transaction(function (Database $db) use (&$intentos): void {
            $this->grupo($db, 100002);
            $db->transaccionConReintento(function (Database $db) use (&$intentos): void {
                $intentos++;
                // The first attempt loses the race (that id exists), the second takes the next one
                $this->grupo($db, $intentos === 1 ? 100001 : 100003);
            });
        });

        $this->assertSame(2, $intentos);
        $this->assertSame([100001, 100002, 100003], $this->grupos($db), $driver);
        $this->assertFalse($db->pdo()->inTransaction());
    }
}
