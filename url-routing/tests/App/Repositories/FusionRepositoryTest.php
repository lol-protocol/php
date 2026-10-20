<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\FusionRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Support\Database;
use App\Support\Privacidad;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/** Merges write, so each test gets its own seeded database. */
class FusionRepositoryTest extends TestCase
{
    private const JUAN = 6128473105;
    private const ELENA = 6128473107;
    private const CARLOS = 6128473108;
    private const MANUEL = 6128473110;
    private const DUPLICADO = 6128473190;
    private const HIJO_DEL_DUPLICADO = 6128473191;
    private const ALIAS_VIEJO = 6128473192;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    /** The demo plus a second Juan that carries a bit of everything. */
    private function conDuplicado(string $driver): Database
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute("INSERT INTO personas (id, nombres, apellidos, sexo, grupo_id, viva) VALUES (?, 'Juan', 'García', 'M', 582317, FALSE)", [self::DUPLICADO]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos, padre_id) VALUES (?, 'Pedro', 'García', ?)", [self::HIJO_DEL_DUPLICADO, self::DUPLICADO]);
        $db->execute("INSERT INTO vinculos (persona_a, persona_b, tipo) VALUES (?, ?, 'otro'), (?, ?, 'conyuge')", [self::DUPLICADO, self::MANUEL, self::ELENA, self::DUPLICADO]);
        $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (412000001, ?, 'testigo')", [self::DUPLICADO]);
        $db->execute("INSERT INTO organizacion_miembros (organizacion_id, persona_id, rol, desde) VALUES (10232, ?, 'feligrés', '1925-02-21')", [self::DUPLICADO]);
        $db->execute('INSERT INTO coleccion_personas (coleccion_id, persona_id) VALUES (1048293, ?)', [self::DUPLICADO]);
        $db->execute('INSERT INTO persona_alias (id, persona_id) VALUES (?, ?)', [self::ALIAS_VIEJO, self::DUPLICADO]);

        return $db;
    }

    #[DataProvider('drivers')]
    public function testResumenCountsWhatWouldMoveWithoutChangingAnything(string $driver): void
    {
        $db = $this->conDuplicado($driver);

        $this->assertSame(
            ['hijos' => 1, 'vinculos' => 2, 'suceso_participantes' => 1, 'organizacion_miembros' => 1, 'coleccion_personas' => 1, 'alias' => 1],
            (new FusionRepository($db))->resumen(self::DUPLICADO)
        );
        $this->assertSame(1, (int)$db->fetchValue('SELECT COUNT(*) FROM personas WHERE id = ?', [self::DUPLICADO]));
    }

    #[DataProvider('drivers')]
    public function testFusionarMovesEverythingAndLeavesAnAlias(string $driver): void
    {
        $db = $this->conDuplicado($driver);
        $valor = static fn(string $sql, array $p = []): int => (int)$db->fetchValue($sql, $p);

        (new FusionRepository($db))->fusionar(self::DUPLICADO, self::JUAN);

        $this->assertSame(0, $valor('SELECT COUNT(*) FROM personas WHERE id = ?', [self::DUPLICADO]));
        $this->assertSame(self::JUAN, $valor('SELECT padre_id FROM personas WHERE id = ?', [self::HIJO_DEL_DUPLICADO]), 'children');
        $this->assertSame(1, $valor("SELECT COUNT(*) FROM vinculos WHERE persona_a = ? AND persona_b = ? AND tipo = 'otro'", [self::JUAN, self::MANUEL]));
        $this->assertSame(1, $valor("SELECT COUNT(*) FROM vinculos WHERE tipo = 'conyuge' AND ((persona_a = ? AND persona_b = ?) OR (persona_a = ? AND persona_b = ?))",
            [self::JUAN, self::ELENA, self::ELENA, self::JUAN]), 'a link both had is kept once');
        $this->assertSame(1, $valor("SELECT COUNT(*) FROM suceso_participantes WHERE suceso_id = 412000001 AND persona_id = ? AND rol = 'testigo'", [self::JUAN]));
        $this->assertSame(1, $valor('SELECT COUNT(*) FROM organizacion_miembros WHERE organizacion_id = 10232 AND persona_id = ?', [self::JUAN]), 'not duplicated');
        $this->assertSame(1, $valor('SELECT COUNT(*) FROM coleccion_personas WHERE coleccion_id = 1048293 AND persona_id = ?', [self::JUAN]), 'not duplicated');
        foreach (['vinculos WHERE persona_a = ? OR persona_b = ?', 'suceso_participantes WHERE persona_id = ? OR persona_id = ?',
            'organizacion_miembros WHERE persona_id = ? OR persona_id = ?', 'coleccion_personas WHERE persona_id = ? OR persona_id = ?'] as $donde) {
            $this->assertSame(0, $valor("SELECT COUNT(*) FROM {$donde}", [self::DUPLICADO, self::DUPLICADO]), $donde);
        }

        $personas = new PersonaRepository($db, Privacidad::propietario());
        $this->assertSame(self::JUAN, $personas->aliasDe(self::DUPLICADO));
        $this->assertSame(self::JUAN, $personas->aliasDe(self::ALIAS_VIEJO), 'an older alias follows along');
    }

    #[DataProvider('drivers')]
    public function testFusionarFillsTheGapsOfTheOneThatStays(string $driver): void
    {
        $db = $this->conDuplicado($driver);

        (new FusionRepository($db))->fusionar(self::DUPLICADO, self::JUAN);

        $this->assertFalse((new PersonaRepository($db, Privacidad::propietario()))->marcaViva(self::JUAN), 'Juan had no viva mark: he takes the duplicate\'s');
        $this->assertSame(6128473103, (int)$db->fetchValue('SELECT padre_id FROM personas WHERE id = ?', [self::JUAN]), 'but keeps his own father');
    }

    /** @return array<string, array{string, int, int}> */
    public static function imposibles(): array
    {
        $casos = [];
        foreach (TestDatabases::drivers() as $driver => [$d]) {
            $casos += [
                "{$driver}: consigo misma" => [$d, self::JUAN, self::JUAN],
                "{$driver}: no existe" => [$d, 6128473189, self::JUAN],
                "{$driver}: hijo y padre" => [$d, self::CARLOS, self::JUAN],
                "{$driver}: conyuges" => [$d, self::ELENA, self::JUAN],
            ];
        }

        return $casos;
    }

    #[DataProvider('imposibles')]
    public function testRefusesWhatCantBeTheSamePersonAndChangesNothing(string $driver, int $duplicado, int $vigente): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $antes = (int)$db->fetchValue('SELECT COUNT(*) FROM personas');

        try {
            (new FusionRepository($db))->fusionar($duplicado, $vigente);
            $this->fail('the merge should have been refused');
        } catch (\DomainException) {
            $this->assertSame($antes, (int)$db->fetchValue('SELECT COUNT(*) FROM personas'));
        }
    }
}
