<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\POS\OrdenRepository;
use App\Repositories\POS\SinStock;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * OrdenRepository::crear() refuses what could never be an order before touching the stock, and asks again only
 * when a write broke on a unique value (Database::transaccionConReintento(): another order took its id or its
 * code at the same moment), never on a CHECK, a foreign key or a NOT NULL.
 *
 * The retries are tested on SQLite with a trigger that makes the INSERT of the order fail the way the
 * database words it, and a function that counts the attempts: PostgreSQL would need two processes racing
 * (RetiroConcurrenciaTest does that for the pedidos, which are numbered the same way).
 */
class OrdenReintentosTest extends TestCase
{
    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    /**
     * What could never be an order. One database per engine for all the cases: the stock must come out the
     * same after each, and every PostgreSQL database a test opens stays connected until the run ends.
     *
     * @return array<string, array<string, int>>
     */
    public static function ordenesImposibles(): array
    {
        return [
            'sin líneas' => [],
            'cantidad cero' => ['CAM-AZ-M' => 0],
            'cantidad negativa' => ['CAM-AZ-M' => -3],
            'una buena y una negativa' => ['GOR-AZ' => 1, 'CAM-AZ-M' => -3],
        ];
    }

    public function testUnaOrdenImposibleSeRechazaAntesDeTocarElStock(): void
    {
        foreach (self::drivers() as [$driver]) {
            $db = TestDatabases::fresh($driver, 'pos', seed: true);
            $stock = $db->fetchAll('SELECT sku, stock FROM variantes ORDER BY sku');
            $ordenes = (int)$db->fetchValue('SELECT COUNT(*) FROM ordenes');

            foreach (self::ordenesImposibles() as $caso => $items) {
                try {
                    (new OrdenRepository($db))->crear($items, 'Calle Prueba 123, Ciudad', 1);
                    $this->fail("{$driver}: {$caso} must be refused");
                } catch (\InvalidArgumentException) {
                }

                $this->assertSame($stock, $db->fetchAll('SELECT sku, stock FROM variantes ORDER BY sku'), "{$driver}: {$caso}: stock untouched");
                $this->assertSame($ordenes, (int)$db->fetchValue('SELECT COUNT(*) FROM ordenes'), "{$driver}: {$caso}: no order was placed");
            }
        }
    }

    /** A SQLite catalog whose INSERTs of an order are counted, and fail on the attempts $fallan with $mensaje. */
    private function catalogoQueFalla(?string $mensaje, int $fallan, int &$intentos): Database
    {
        $db = TestDatabases::fresh('sqlite', 'pos', seed: true);
        $pdo = $db->pdo();
        $pdo->sqliteCreateFunction('cuenta_intento', static function () use (&$intentos): int {
            return ++$intentos;
        }, 0);
        $falla = $mensaje === null ? '' : "SELECT RAISE(ABORT, '{$mensaje}') WHERE cuenta_intento() <= {$fallan};";
        $cuenta = $mensaje === null ? 'SELECT cuenta_intento();' : '';
        $pdo->exec("CREATE TEMP TRIGGER cuenta_ordenes BEFORE INSERT ON ordenes BEGIN {$falla}{$cuenta} END");

        return $db;
    }

    public function testUnChoqueDeIdOCodigoSeReintentaYElSegundoIntentoLoLogra(): void
    {
        $intentos = 0;
        $db = $this->catalogoQueFalla('UNIQUE constraint failed: ordenes.id', 1, $intentos);
        $ordenes = (int)$db->fetchValue('SELECT COUNT(*) FROM ordenes');

        $orden = (new OrdenRepository($db))->crear(['CAM-AZ-M' => 2], 'Calle Prueba 123, Ciudad', 1);

        $this->assertSame(2, $intentos, 'the first INSERT clashed, the second went through');
        $this->assertSame($ordenes + 1, (int)$db->fetchValue('SELECT COUNT(*) FROM ordenes'), 'one order, not one per attempt');
        $this->assertSame(28, (int)$db->fetchValue("SELECT stock FROM variantes WHERE sku = 'CAM-AZ-M'"), 'the stock went down once, not once per attempt');
        $this->assertMatchesRegularExpression('/^[1-9]\d{15}$/', $orden['codigo']);
    }

    public function testElChoqueDelCodigoTambienSeReintenta(): void
    {
        $intentos = 0;
        $db = $this->catalogoQueFalla('UNIQUE constraint failed: ordenes.codigo', 2, $intentos);

        (new OrdenRepository($db))->crear(['CAM-AZ-M' => 1], 'Calle Prueba 123, Ciudad', 1);

        $this->assertSame(3, $intentos);
    }

    public function testUnChoqueQueNoSeVaAcabandoSeRindeAlTercerIntento(): void
    {
        $intentos = 0;
        $db = $this->catalogoQueFalla('UNIQUE constraint failed: ordenes.id', 99, $intentos);

        try {
            (new OrdenRepository($db))->crear(['CAM-AZ-M' => 2], 'Calle Prueba 123, Ciudad', 1);
            $this->fail('after three attempts the error goes on');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('ordenes.id', $e->getMessage());
        }

        $this->assertSame(3, $intentos);
        $this->assertSame(30, (int)$db->fetchValue("SELECT stock FROM variantes WHERE sku = 'CAM-AZ-M'"), 'nothing was left half done');
    }

    /** @return array<string, array{string}> */
    public static function erroresQueNoSonElChoque(): array
    {
        return [
            'un CHECK' => ['CHECK constraint failed: cantidad > 0'],
            'una llave foránea' => ['FOREIGN KEY constraint failed'],
            'un NOT NULL' => ['NOT NULL constraint failed: ordenes.moneda'],
        ];
    }

    #[DataProvider('erroresQueNoSonElChoque')]
    public function testUnErrorQueNoEsElChoqueNoSeReintenta(string $mensaje): void
    {
        $intentos = 0;
        $db = $this->catalogoQueFalla($mensaje, 99, $intentos);

        try {
            (new OrdenRepository($db))->crear(['CAM-AZ-M' => 2], 'Calle Prueba 123, Ciudad', 1);
            $this->fail('it must fail');
        } catch (\PDOException $e) {
            $this->assertStringContainsString($mensaje, $e->getMessage());
        }

        $this->assertSame(1, $intentos, 'asking again would fail the same way');
    }

    public function testUnUsuarioQueNoExisteFallaUnaSolaVezEnSQLite(): void
    {
        $intentos = 0;
        $db = $this->catalogoQueFalla(null, 0, $intentos);

        try {
            (new OrdenRepository($db))->crear(['CAM-AZ-M' => 2], 'Calle Prueba 123, Ciudad', 999999);
            $this->fail('the user does not exist');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }

        $this->assertSame(1, $intentos, 'SQLite words every constraint 23000: this one must not be asked again either');
        $this->assertSame(30, (int)$db->fetchValue("SELECT stock FROM variantes WHERE sku = 'CAM-AZ-M'"));
    }

    public function testSinStockSigueSiendoSinStock(): void
    {
        $db = TestDatabases::fresh('sqlite', 'pos', seed: true);

        $this->expectException(SinStock::class);
        (new OrdenRepository($db))->crear(['SUD-AZ-L' => 4], 'Calle Prueba 123, Ciudad', 1);
    }
}
