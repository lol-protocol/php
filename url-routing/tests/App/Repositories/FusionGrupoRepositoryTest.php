<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\FusionGrupoRepository;
use App\Repositories\Genealogy\GrupoRepository;
use App\Repositories\Genealogy\RetiroRepository;
use App\Support\Database;
use App\Support\Privacidad;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/** Merges write, so each test gets its own seeded database. */
class FusionGrupoRepositoryTest extends TestCase
{
    private const GARCIA = 582317;
    private const HERNANDEZ = 582318;
    private const DUPLICADO = 582399;
    private const ALIAS_VIEJO = 582398;
    private const PERSONA_DEL_DUPLICADO = 6128473191;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    /** The demo plus a second «Garcia» (no accent) that has a person, an origin and an older alias. */
    private function conDuplicado(string $driver): Database
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute("INSERT INTO grupos (id, apellido, origen, descripcion) VALUES (?, 'Garcia', 'mx', 'Escrito sin tilde.')", [self::DUPLICADO]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos, grupo_id) VALUES (?, 'Ana', 'Garcia', ?)", [self::PERSONA_DEL_DUPLICADO, self::DUPLICADO]);
        $db->execute('INSERT INTO grupo_alias (id, grupo_id) VALUES (?, ?)', [self::ALIAS_VIEJO, self::DUPLICADO]);

        return $db;
    }

    #[DataProvider('drivers')]
    public function testResumenCountsWhatWouldMoveWithoutChangingAnything(string $driver): void
    {
        $db = $this->conDuplicado($driver);

        $this->assertSame(['personas' => 1, 'alias' => 1], (new FusionGrupoRepository($db))->resumen(self::DUPLICADO));
        $this->assertSame(1, (int)$db->fetchValue('SELECT COUNT(*) FROM grupos WHERE id = ?', [self::DUPLICADO]));
    }

    #[DataProvider('drivers')]
    public function testFusionarMovesThePersonasAndLeavesAnAlias(string $driver): void
    {
        $db = $this->conDuplicado($driver);

        (new FusionGrupoRepository($db))->fusionar(self::DUPLICADO, self::GARCIA);

        $this->assertSame(0, (int)$db->fetchValue('SELECT COUNT(*) FROM grupos WHERE id = ?', [self::DUPLICADO]));
        $this->assertSame(self::GARCIA, (int)$db->fetchValue('SELECT grupo_id FROM personas WHERE id = ?', [self::PERSONA_DEL_DUPLICADO]));

        $grupos = new GrupoRepository($db, Privacidad::propietario());
        $this->assertSame(self::GARCIA, $grupos->aliasDe(self::DUPLICADO), 'the merged id points to the one that stays');
        $this->assertSame(self::GARCIA, $grupos->aliasDe(self::ALIAS_VIEJO), 'so does an older alias of the duplicate');
        $this->assertNull($grupos->aliasDe(self::GARCIA), 'the one that stays is no alias');
        $this->assertNull($grupos->aliasDe(999999), 'an id that never existed is no alias');
    }

    #[DataProvider('drivers')]
    public function testTheOneThatStaysKeepsWhatItHasAndTakesWhatItLacks(string $driver): void
    {
        $db = $this->conDuplicado($driver);
        $db->execute('UPDATE grupos SET origen = NULL, descripcion = NULL WHERE id = ?', [self::HERNANDEZ]);
        $db->execute("UPDATE grupos SET descripcion = 'La mía.' WHERE id = ?", [self::HERNANDEZ]);

        (new FusionGrupoRepository($db))->fusionar(self::DUPLICADO, self::HERNANDEZ);

        $fila = $db->fetchOne('SELECT origen, descripcion FROM grupos WHERE id = ?', [self::HERNANDEZ]);
        $this->assertSame('mx', $fila['origen'], 'a gap is filled from the duplicate');
        $this->assertSame('La mía.', $fila['descripcion'], 'what it had stays');
    }

    #[DataProvider('drivers')]
    public function testAGroupCannotBeMergedIntoItself(string $driver): void
    {
        $db = $this->conDuplicado($driver);

        $this->expectException(\DomainException::class);
        (new FusionGrupoRepository($db))->fusionar(self::GARCIA, self::GARCIA);
    }

    #[DataProvider('drivers')]
    public function testBothHaveToExistAndNothingChangesIfOneDoesNot(string $driver): void
    {
        $db = $this->conDuplicado($driver);

        try {
            (new FusionGrupoRepository($db))->fusionar(self::DUPLICADO, 111111);
            $this->fail('A merge into a grupo that does not exist went through');
        } catch (\DomainException) {
            $this->assertSame(1, (int)$db->fetchValue('SELECT COUNT(*) FROM grupos WHERE id = ?', [self::DUPLICADO]));
            $this->assertSame(self::DUPLICADO, (int)$db->fetchValue('SELECT grupo_id FROM personas WHERE id = ?', [self::PERSONA_DEL_DUPLICADO]));
        }
    }

    /** A page the owner hid keeps answering 410 on its old id: merging it away would bury that decision. */
    #[DataProvider('drivers')]
    public function testAGroupWithAWithdrawalPedidoIsNotMerged(string $driver): void
    {
        $db = $this->conDuplicado($driver);
        $pedidos = new RetiroRepository($db);
        $pedidos->decidir($pedidos->crear('grupo', self::DUPLICADO, 'otro', 'x', 'c@example.com'), 'ocultar', null);

        try {
            (new FusionGrupoRepository($db))->fusionar(self::DUPLICADO, self::GARCIA);
            $this->fail('A hidden grupo was merged away');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('pedido de retiro', $e->getMessage());
            $this->assertSame(1, (int)$db->fetchValue('SELECT COUNT(*) FROM grupos WHERE id = ?', [self::DUPLICADO]));
        }
    }
}
