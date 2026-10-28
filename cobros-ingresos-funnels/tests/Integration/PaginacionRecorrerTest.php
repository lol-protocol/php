<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Paginacion;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Paginacion::recorrer(): todas las filas de una consulta, de a un lote. A proposito NO
 * extiende IntegracionTestCase: lo que se prueba es justamente que abre su propia
 * transaccion cuando no hay una, y que usa la de afuera cuando la hay.
 */
final class PaginacionRecorrerTest extends TestCase
{
    private const DESDE = 'generate_series(1, 10) AS g(n) WHERE g.n > :minimo';

    protected function tearDown(): void
    {
        $db = Database::connection();
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        parent::tearDown();
    }

    /** @return list<int> */
    private static function numeros(int $lote, string $minimo = '3'): array
    {
        $filas = Paginacion::recorrer(Database::connection(), 'g.n', self::DESDE, 'g.n DESC', [':minimo' => $minimo], $lote);

        return array_map(static fn (array $fila): int => (int) $fila['n'], iterator_to_array($filas, false));
    }

    public function testDaTodasLasFilasEnElOrdenPedidoYRespetaLosParametros(): void
    {
        self::assertSame([10, 9, 8, 7, 6, 5, 4], self::numeros(1000));
    }

    public function testElTamanoDelLoteNoCambiaLoQueSale(): void
    {
        $esperado = [10, 9, 8, 7, 6, 5, 4];

        foreach ([1, 2, 3, 7, 8, 1000] as $lote) {
            self::assertSame($esperado, self::numeros($lote), "lote de {$lote}");
        }
    }

    public function testUnaConsultaSinFilasNoDaNada(): void
    {
        self::assertSame([], self::numeros(1000, '999'));
    }

    public function testSinTransaccionDeAfueraAbreUnaYLaCierraAlTerminar(): void
    {
        $db = Database::connection();
        self::assertFalse($db->inTransaction());

        $recorrido = Paginacion::recorrer($db, 'g.n', self::DESDE, 'g.n', [':minimo' => '0'], 3);
        $recorrido->current();
        self::assertTrue($db->inTransaction(), 'un cursor solo vive dentro de una transaccion');

        iterator_to_array($recorrido, false);
        self::assertFalse($db->inTransaction(), 'al agotarse, la transaccion que abrio se deshace');
    }

    public function testSiQuienLoRecorreSeCortaTambienSeCierra(): void
    {
        $db = Database::connection();
        $recorrido = Paginacion::recorrer($db, 'g.n', self::DESDE, 'g.n', [':minimo' => '0'], 2);

        foreach ($recorrido as $fila) {
            break;   // se corta a la primera fila
        }
        self::assertTrue($db->inTransaction(), 'mientras el recorrido siga vivo, el cursor sigue abierto');

        unset($recorrido);   // el generador se destruye y corre su finally
        self::assertFalse($db->inTransaction(), 'al destruirse, la transaccion se cierra aunque no se haya agotado');
    }

    public function testDentroDeUnaTransaccionDeAfueraUsaEsaYSoloCierraSuCursor(): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        $primero = Paginacion::recorrer($db, 'g.n', self::DESDE, 'g.n', [':minimo' => '0'], 2);
        foreach ($primero as $fila) {
            break;
        }
        unset($primero);
        self::assertTrue($db->inTransaction(), 'no cierra una transaccion que no abrio');

        // Y se puede recorrer otro despues: cada recorrido tiene su propio cursor.
        $otro = Paginacion::recorrer($db, 'g.n', self::DESDE, 'g.n DESC', [':minimo' => '8'], 2);
        self::assertSame([10, 9], array_map(static fn (array $f): int => (int) $f['n'], iterator_to_array($otro, false)));
        self::assertTrue($db->inTransaction());

        $db->rollBack();
    }

    public function testSiQuienLoRecorreTerminoLaTransaccionNoFallaAlCerrar(): void
    {
        $db = Database::connection();
        $recorrido = Paginacion::recorrer($db, 'g.n', self::DESDE, 'g.n', [':minimo' => '0'], 2);

        foreach ($recorrido as $fila) {
            $db->rollBack();   // quien recorre termina la transaccion que abrio el recorrido
            break;
        }

        unset($recorrido);     // el finally no tiene nada que deshacer y no tira "no active transaction"
        self::assertFalse($db->inTransaction());
    }

    public function testUnErrorDeLaConsultaSePropagaYNoDejaLaTransaccionAbierta(): void
    {
        $db = Database::connection();

        try {
            iterator_to_array(Paginacion::recorrer($db, 'x.id', 'tabla_que_no_existe x', 'x.id', []), false);
            self::fail('tenia que fallar: la tabla no existe');
        } catch (PDOException $e) {
            self::assertStringContainsString('tabla_que_no_existe', $e->getMessage());
        }

        self::assertFalse($db->inTransaction(), 'la transaccion que abrio no queda colgada');
    }
}
