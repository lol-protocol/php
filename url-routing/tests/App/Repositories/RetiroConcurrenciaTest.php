<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\RetiroRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * Two people asking for a withdrawal at the same moment, on PostgreSQL (SQLite has a single writer, so
 * the race cannot happen there).
 *
 * crear() numbers a pedido with MAX(id) + 1. Both people read the same maximum, both try the same number,
 * and the second INSERT breaks the primary key: without a retry, that person gets an error page and their
 * complaint is lost. The "other person" here is a second process that takes number 1 and holds its
 * transaction open for a moment, so the real database is the one that decides who wins.
 */
class RetiroConcurrenciaTest extends TestCase
{
    private ?string $script = null;

    protected function tearDown(): void
    {
        if ($this->script !== null) {
            @unlink($this->script);
        }
    }

    protected function setUp(): void
    {
        if (!getenv('TEST_PG_DSN')) {
            $this->markTestSkipped('Needs TEST_PG_DSN: SQLite has one writer at a time, so there is no race to test.');
        }
    }

    /** Takes number $argv[2] in a transaction that stays open $argv[3] ms, as another request would. */
    private function otraPersona(string $schema, int $numero, int $milisegundos): mixed
    {
        $this->script = tempnam(sys_get_temp_dir(), 'otra_persona_') . '.php';
        file_put_contents($this->script, <<<'PHP'
            <?php
            $pdo = new PDO((string)getenv('TEST_PG_DSN'), (string)getenv('TEST_PG_USER'), (string)getenv('TEST_PG_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET search_path TO ' . $argv[1]);
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO pedidos_retiro (id, tipo, recurso_id, motivo, detalle, contacto, creado_en)
                           VALUES (?, 'persona', 6128473106, 'otro', 'Otro pedido, a la vez.', 'otra@example.com', ?)")
                ->execute([(int)$argv[2], date('Y-m-d H:i:s')]);
            echo "LISTO\n";
            fflush(STDOUT);
            usleep((int)$argv[3] * 1000);
            $pdo->commit();
            PHP);

        $proceso = proc_open(
            ['php', $this->script, $schema, (string)$numero, (string)$milisegundos],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            getenv()
        );
        $this->assertIsResource($proceso);
        stream_set_timeout($pipes[1], 15);
        $this->assertSame("LISTO\n", fgets($pipes[1]), 'the other person took their number');

        return [$proceso, $pipes];
    }

    private function terminar(mixed $proceso, array $pipes): void
    {
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($proceso), 'the other person commits: ' . $error);
    }

    public function testTwoPeopleAskingAtTheSameTimeBothGetANumber(): void
    {
        $db = TestDatabases::fresh('pgsql', 'genealogy', seed: true);
        $repo = new RetiroRepository($db);
        [$proceso, $pipes] = $this->otraPersona((string)$db->fetchValue('SELECT current_schema()'), 1, 1000);

        // The other person has number 1 but has not committed: this one reads MAX(id) = 0 and tries 1 too.
        $numero = $repo->crear('persona', 6128473105, 'me_nombra', 'Es falso.', 'a@example.com');
        $this->terminar($proceso, $pipes);

        $this->assertSame(2, $numero, 'it asked again and got the next number');
        $this->assertSame([1, 2], array_map('intval', array_column($db->fetchAll('SELECT id FROM pedidos_retiro ORDER BY id'), 'id')), 'both complaints are there');
        $this->assertSame(
            ['recibido'],
            array_column($db->fetchAll('SELECT estado FROM pedido_retiro_eventos WHERE pedido_id = 2'), 'estado'),
            'and the second has its record'
        );
        $this->assertSame(6128473105, (int)$db->fetchValue('SELECT recurso_id FROM pedidos_retiro WHERE id = 2'), 'about its own page');
    }

    public function testAnErrorThatIsNotARaceIsNotRetried(): void
    {
        $db = TestDatabases::fresh('pgsql', 'genealogy', seed: true);
        $repo = new RetiroRepository($db);

        try {
            // 'lugar' is not a type that can be reported: the CHECK refuses it, and asking again would change nothing.
            $repo->crear('lugar', 1, 'otro', 'x', 'a@example.com');
            $this->fail('the CHECK should have refused it');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('check', strtolower($e->getMessage()));
        }

        $this->assertSame(0, (int)$db->fetchValue('SELECT COUNT(*) FROM pedidos_retiro'), 'nothing was stored');
    }
}
