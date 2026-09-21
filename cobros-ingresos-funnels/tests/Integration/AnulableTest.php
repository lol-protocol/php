<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Corre contra la base configurada por las env vars DB_*. Crea su propia
 * boleta de prueba y la borra en el tearDown: es un dato de laboratorio, no
 * una entidad de negocio a la que corresponda el soft-delete.
 */
final class AnulableTest extends TestCase
{
    private ?int $boletaId = null;

    protected function tearDown(): void
    {
        if ($this->boletaId === null) {
            return;
        }
        Database::connection()
            ->prepare('DELETE FROM boletas WHERE id = :id')
            ->execute([':id' => $this->boletaId]);
        $this->boletaId = null;
    }

    /** Otra conexion a la misma base, para poder representar a un segundo proceso. */
    private static function segundaConexion(): PDO
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '5432';
        $nombre = getenv('DB_NAME') ?: 'cobros_ingresos_funnels';

        $pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname={$nombre}",
            getenv('DB_USER') ?: 'cobros_app',
            getenv('DB_PASSWORD') ?: 'cobros_app_dev'
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function crearBoletaDePrueba(): int
    {
        $cliente = Database::connection()
            ->query('SELECT id, pais_codigo FROM clientes ORDER BY id LIMIT 1')
            ->fetch();
        self::assertNotFalse($cliente, 'este test asume que el seed dejo al menos un cliente');

        $this->boletaId = (new BoletaRepository())->crear([
            'cliente_id' => $cliente['id'],
            'concepto' => 'Boleta de prueba de concurrencia',
            'monto' => 100.00,
            'moneda_codigo' => 'USD',
            'fecha_emision' => '2020-01-01',
            'fecha_vencimiento' => '2020-02-01',
        ]);

        return $this->boletaId;
    }

    public function testDevuelveTrueLaPrimeraVezYFalseDespues(): void
    {
        $repo = new BoletaRepository();
        $id = $this->crearBoletaDePrueba();

        self::assertTrue($repo->anularSiEstabaActiva($id), 'la primera anulacion es la que aplica');
        self::assertFalse($repo->anularSiEstabaActiva($id), 'la segunda no encuentra nada que anular');
    }

    /**
     * Reproduce la carrera real: dos anulaciones simultaneas de la misma
     * boleta pasaban las dos la guarda "if (!$boleta['anulada'])" -leida
     * antes de la transaccion- y emitian dos notas de credito, o sea le
     * devolvian la plata al cliente dos veces.
     *
     * Con el chequeo dentro de la propia sentencia, el segundo proceso queda
     * esperando la fila hasta que el primero commitea (aca se ve como
     * lock_timeout, porque el test es de un solo hilo y no puede esperar), y
     * cuando reevalua la condicion ya no matchea nada.
     */
    public function testUnaAnulacionEnCursoBloqueaALaOtraYLuegoNoAplica(): void
    {
        $repo = new BoletaRepository();
        $id = $this->crearBoletaDePrueba();

        $otroProceso = self::segundaConexion();
        $otroProceso->exec("SET lock_timeout = '500ms'");

        $db = Database::connection();
        $db->beginTransaction();
        try {
            self::assertTrue($repo->anularSiEstabaActiva($id));

            $otroProceso->beginTransaction();
            try {
                $otroProceso
                    ->prepare('UPDATE boletas SET anulada = TRUE WHERE id = :id AND NOT anulada')
                    ->execute([':id' => $id]);
                self::fail('la segunda anulacion tendria que haber quedado bloqueada por la primera');
            } catch (PDOException $e) {
                self::assertStringContainsString('lock timeout', strtolower($e->getMessage()));
            } finally {
                $otroProceso->rollBack();
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $stmt = $otroProceso->prepare('UPDATE boletas SET anulada = TRUE WHERE id = :id AND NOT anulada RETURNING id');
        $stmt->execute([':id' => $id]);
        self::assertFalse($stmt->fetchColumn(), 'ya commiteada la primera, la segunda no aplica y no emite su nota');
    }
}
