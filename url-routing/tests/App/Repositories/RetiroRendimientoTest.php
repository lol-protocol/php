<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\PersonaRepository;
use App\Support\Privacidad;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * "Is this persona withdrawn?" (Privacidad::sqlRetirada) is asked for every persona a query looks at, so
 * it has to be a lookup in pedidos_retiro_recurso_idx and not a scan of the table, and a page of the
 * search must keep costing what a page costs and not what the catalog (or the number of pedidos) weighs.
 */
class RetiroRendimientoTest extends TestCase
{
    private const PERSONAS = 3000;
    private const PEDIDOS = 400;

    private function catalogo(): \App\Support\Database
    {
        $db = TestDatabases::fresh('sqlite', 'genealogy');
        $pdo = $db->pdo();
        $pdo->beginTransaction();
        $persona = $pdo->prepare('INSERT INTO personas (id, nombres, apellidos) VALUES (?, ?, ?)');
        $suceso = $pdo->prepare("INSERT INTO sucesos (id, tipo, fecha) VALUES (?, 'nacimiento', '1850-05-05')");
        $participa = $pdo->prepare("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')");
        $pedido = $pdo->prepare("INSERT INTO pedidos_retiro (id, tipo, recurso_id, motivo, detalle, contacto, estado, creado_en) VALUES (?, 'persona', ?, 'otro', 'x', 'c@example.com', ?, '2026-10-01 00:00:00')");
        for ($i = 1; $i <= self::PERSONAS; $i++) {
            $id = 1_000_000_000 + $i;
            $persona->execute([$id, 'Nombre' . str_pad((string)$i, 4, '0', STR_PAD_LEFT), 'Apellido' . str_pad((string)($i % 7), 2, '0', STR_PAD_LEFT)]);
            $suceso->execute([100_000_000 + $i]);
            $participa->execute([100_000_000 + $i, $id]);
            if ($i <= self::PEDIDOS) {
                $pedido->execute([$i, $id, $i % 2 === 0 ? 'retirado' : 'rechazado']);
            }
        }
        $pdo->commit();

        return $db;
    }

    public function testTheWithdrawalLookupUsesItsIndex(): void
    {
        $db = $this->catalogo();
        $plan = implode("\n", array_column(
            $db->fetchAll('EXPLAIN QUERY PLAN SELECT q.id FROM personas q WHERE ' . Privacidad::sqlRetirada('q')),
            'detail'
        ));

        $this->assertStringContainsString('pedidos_retiro_recurso_idx', $plan, "The lookup scans pedidos_retiro:\n{$plan}");
    }

    public function testAPageOfTheSearchDoesNotReadTheCatalogNorThePedidos(): void
    {
        $db = $this->catalogo();
        $pdo = $db->pdo();
        $llamadas = 0;
        $pdo->sqliteCreateFunction('translate', static function (?string $texto, string $de, string $a) use (&$llamadas): ?string {
            $llamadas++;

            return \App\Support\Database::traducir($texto, $de, $a);
        }, 3);

        $filas = (new PersonaRepository($db, Privacidad::publica()))->buscar('', 50);

        $this->assertCount(50, $filas);
        // Half of the first pedidos withdrew their persona: the 50 rows come from further down, still a handful of reads.
        $this->assertLessThan(500, $llamadas, "read {$llamadas} rows of " . self::PERSONAS . ' to return 50');
        $retiradas = array_column(array_filter($db->fetchAll("SELECT recurso_id FROM pedidos_retiro WHERE estado = 'retirado'")), 'recurso_id');
        $this->assertSame([], array_intersect(array_map('intval', array_column($filas, 'id')), array_map('intval', $retiradas)), 'none of the 50 is withdrawn');
    }

    public function testTheWholeVisitorsViewOfPersonasStaysFast(): void
    {
        $db = $this->catalogo();
        $inicio = hrtime(true);
        $visibles = (int)$db->fetchValue('SELECT COUNT(*) FROM ' . Privacidad::publica(new \DateTimeImmutable('2026-10-09'))->personas() . ' p WHERE NOT p.oculta');
        $segundos = (hrtime(true) - $inicio) / 1e9;

        $this->assertSame(self::PERSONAS - self::PEDIDOS / 2, $visibles, 'withdrawn personas are hidden, the rest are not');
        // A loose bound: it catches the lookup turning into a scan per persona (minutes), not a slower machine.
        $this->assertLessThan(20.0, $segundos, "personas() over " . self::PERSONAS . " personas and " . self::PEDIDOS . " pedidos took {$segundos} s");
    }
}
