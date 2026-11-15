<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\RetiroRepository;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * Los pedidos de retiro son lo único del sitio que no se puede regenerar desde los datos de siempre: son lo que
 * se recibió, lo que se decidió y cuándo. Esta prueba hace lo que haría quien restaura una copia a mano: vuelca
 * las dos tablas con todas sus columnas, las inserta en una base vacía recién migrada, y comprueba que el
 * repositorio ve lo mismo y que el sitio sigue funcionando (numeración, historial, avisos, depuración).
 */
class RetiroRespaldoTest extends TestCase
{
    private const TABLAS = [
        'pedidos_retiro' => 'id',
        'pedido_retiro_eventos' => 'pedido_id, orden',
    ];

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    #[DataProvider('drivers')]
    public function testARestoredCopyIsTheSameRecordAndKeepsWorking(string $driver): void
    {
        $origen = TestDatabases::fresh($driver, 'genealogy');
        $repo = new RetiroRepository($origen);

        $retirado = $repo->crear('persona', 6128473105, 'me_nombra', 'Es falso.', 'a@example.com', 'insulto');
        $oculto = $repo->crear('grupo', 582317, 'otro', 'Otro.', 'b@example.com');
        $pendiente = $repo->crear('registro', 81372002, 'terminos', 'Tres.', 'c@example.com');
        $repo->decidir($retirado, 'retirar', 'Se retiró.');
        $repo->decidir($oculto, 'ocultar', 'Lo reviso.');
        $repo->avisarNuevos();
        // The retired one is purged (its mail and text erased, its history kept); the other two are still under review.
        $this->assertSame([$retirado], $repo->depurar(12, strtotime('+2 years')));

        $volcado = self::volcar($origen);
        $this->assertCount(3, $volcado['pedidos_retiro']);
        $this->assertNotEmpty($volcado['pedido_retiro_eventos']);

        $destino = TestDatabases::fresh($driver, 'genealogy');
        self::restaurar($destino, $volcado);
        $this->assertSame($volcado, self::volcar($destino), 'every column of every row comes back, dates and states included');

        $copia = new RetiroRepository($destino);
        $this->assertSame($repo->todos(), $copia->todos(), 'the owner queue and every history read the same');
        $this->assertTrue($copia->retirado('persona', 6128473105));
        $this->assertTrue($copia->retirado('grupo', 582317));
        $this->assertFalse($copia->retirado('registro', 81372002), 'a pending pedido hides nothing, before or after');

        $this->assertSame([], $copia->avisarNuevos(), 'what was announced is not announced again');
        $fila = $destino->fetchOne('SELECT detalle, contacto, depurado_en FROM pedidos_retiro WHERE id = ?', [$retirado]);
        $this->assertSame(['', ''], [$fila['detalle'], $fila['contacto']], 'a purged pedido does not get its data back');
        $this->assertNotNull($fila['depurado_en']);

        $siguiente = $copia->crear('persona', 6128473105, 'familiar', 'Nuevo.', 'd@example.com');
        $this->assertSame($pendiente + 1, $siguiente, 'numbering goes on after the restored ones');
        $this->assertTrue($copia->decidir($oculto, 'retirar', null));
        $this->assertSame(
            ['recibido', 'oculto', 'retirado'],
            array_column($destino->fetchAll('SELECT estado FROM pedido_retiro_eventos WHERE pedido_id = ? ORDER BY orden', [$oculto]), 'estado'),
            'the history continues where it was'
        );
    }

    #[DataProvider('drivers')]
    public function testTheHistoryCannotBeRestoredBeforeItsPedidos(string $driver): void
    {
        $origen = TestDatabases::fresh($driver, 'genealogy');
        (new RetiroRepository($origen))->crear('persona', 6128473105, 'otro', 'Texto.', 'a@example.com');
        $volcado = self::volcar($origen);

        $destino = TestDatabases::fresh($driver, 'genealogy');
        $this->expectException(\PDOException::class);
        $destino->insert('pedido_retiro_eventos', $volcado['pedido_retiro_eventos'][0]);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private static function volcar(Database $db): array
    {
        $volcado = [];
        foreach (self::TABLAS as $tabla => $orden) {
            $volcado[$tabla] = $db->fetchAll("SELECT * FROM {$tabla} ORDER BY {$orden}");
        }

        return $volcado;
    }

    /** @param array<string, list<array<string, mixed>>> $volcado Parents first: the history points at its pedido. */
    private static function restaurar(Database $db, array $volcado): void
    {
        $db->transaction(static function () use ($db, $volcado): void {
            foreach (array_keys(self::TABLAS) as $tabla) {
                foreach ($volcado[$tabla] as $fila) {
                    $db->insert($tabla, $fila);
                }
            }
        });
    }
}
