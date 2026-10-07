<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use Throwable;

final class Database
{
    private static ?PDO $connection = null;

    /** Cuantos SAVEPOINT anidados hay abiertos ahora mismo, para darle a cada uno un nombre propio. */
    private static int $savepointsAbiertos = 0;

    /** La conexion compartida del request (o del proceso de CLI). */
    public static function connection(): PDO
    {
        return self::$connection ??= self::conectar(self::debeSerPersistente());
    }

    /**
     * En la web (PHP-FPM, php -S) la conexion se reutiliza entre peticiones:
     * abrirla cuesta entre 10 y 20 ms con una base local (mas con una remota),
     * mas que todo lo demas que hace una pantalla liviana (Auditoria pasa de 14
     * a 3 ms). En CLI -migraciones, seed y tests- nunca: ahi cada conexion tiene
     * que ser propia. DB_PERSISTENT=0 la apaga, por ejemplo detras de un pooler
     * como PgBouncer, o si hay mas procesos de PHP que max_connections en
     * Postgres (cada uno guarda su conexion abierta).
     */
    private static function debeSerPersistente(): bool
    {
        return PHP_SAPI !== 'cli' && Config::variable('DB_PERSISTENT', '1') !== '0';
    }

    /**
     * Una conexion nueva, independiente de la compartida. La usa connection()
     * la primera vez, y los tests que necesitan hacer de segundo proceso (dos
     * anulaciones simultaneas, dos envios del mismo formulario): por eso, sin
     * pedirlo, no es persistente (una persistente devolveria la misma que ya
     * tienen).
     *
     * Con $persistente la conexion queda en el proceso y la siguiente peticion
     * la reutiliza. Lo que se podia temer -que una peticion que murio a mitad de
     * una transaccion le deje su estado a la siguiente- no pasa: PDO deshace sola
     * la transaccion pendiente cuando se la suelta. Lo que si pasa es que la
     * conexion guardada puede estar muerta (se reinicio Postgres, o corto las
     * inactivas): el primer comando falla con "SSL connection has been closed
     * unexpectedly", que sin reintento era un 500 para la primera peticion de
     * cada proceso. Por eso se reintenta una vez, ya con una conexion nueva
     * (DatabasePersistenteTest).
     *
     * Tambien fija la zona horaria, la de PHP y la de la sesion de Postgres a
     * la vez y desde el mismo valor: es el unico punto por el que pasan la
     * web, el seed, las migraciones y los tests de integracion. Antes no la
     * fijaba nadie y cada reloj usaba la suya: EstadoBoleta decide "vencida"
     * con date() de PHP y el grafico de antiguedad con CURRENT_DATE de
     * Postgres, asi que si php.ini y el servidor de base diferian, la misma
     * boleta figuraba vencida en un lado y al dia en el otro durante algunas
     * horas por dia.
     */
    public static function conectar(bool $persistente = false): PDO
    {
        $zona = Config::zonaHoraria();
        date_default_timezone_set($zona);

        // Host y puerto tienen un valor estandar que sirve en cualquier
        // entorno; nombre, usuario y clave no, y fuera de desarrollo son
        // obligatorios.
        ['DB_NAME' => $nombre, 'DB_USER' => $usuario, 'DB_PASSWORD' => $clave] = Config::variablesObligatorias([
            'DB_NAME' => 'cobros_ingresos_funnels',
            'DB_USER' => 'cobros_app',
            'DB_PASSWORD' => 'cobros_app_dev',
        ]);

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            Config::variable('DB_HOST', '127.0.0.1'),
            Config::variable('DB_PORT', '5432'),
            $nombre
        );

        try {
            return self::abrir($dsn, $usuario, $clave, $persistente, $zona);
        } catch (PDOException $e) {
            if (!$persistente) {
                throw $e;
            }

            // La conexion guardada estaba muerta: PDO la descarto al fallar, el segundo intento abre una nueva.
            // Si la base de verdad no esta, este tambien falla y el error sube igual.
            return self::abrir($dsn, $usuario, $clave, true, $zona);
        }
    }

    private static function abrir(string $dsn, string $usuario, string $clave, bool $persistente, string $zona): PDO
    {
        $pdo = new PDO($dsn, $usuario, $clave, [PDO::ATTR_PERSISTENT => $persistente]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('SET TIME ZONE ' . $pdo->quote($zona));

        return $pdo;
    }

    /**
     * Corre $operacion dentro de una transaccion y devuelve lo que ella
     * devuelva: o quedan todas sus escrituras o ninguna. Para los flujos que
     * escriben mas de una fila relacionada (ej. anular una boleta y emitir su
     * nota de credito, o cualquier cambio junto con su entrada de auditoria).
     * La excepcion se vuelve a lanzar tras el rollback, para que la maneje el
     * ErrorHandler global.
     *
     * Si ya hay una transaccion abierta -una operacion que llama a otra, o un
     * test que envuelve todo para deshacerlo al final- se anida con un
     * SAVEPOINT en vez de fallar: PDO no admite dos beginTransaction()
     * seguidos. Si la operacion anidada falla, se deshace solo lo suyo y la
     * de afuera decide que hacer con la excepcion.
     *
     * @template T
     * @param callable(): T $operacion
     * @return T
     */
    public static function transaccion(callable $operacion): mixed
    {
        $db = self::connection();
        if ($db->inTransaction()) {
            return self::conSavepoint($db, $operacion);
        }

        $db->beginTransaction();
        try {
            $resultado = $operacion();
            $db->commit();
            return $resultado;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * @template T
     * @param callable(): T $operacion
     * @return T
     */
    private static function conSavepoint(PDO $db, callable $operacion): mixed
    {
        $nombre = 'anidada_' . (++self::$savepointsAbiertos);
        $db->exec("SAVEPOINT {$nombre}");
        try {
            $resultado = $operacion();
            $db->exec("RELEASE SAVEPOINT {$nombre}");
            return $resultado;
        } catch (Throwable $e) {
            $db->exec("ROLLBACK TO SAVEPOINT {$nombre}");
            $db->exec("RELEASE SAVEPOINT {$nombre}");
            throw $e;
        } finally {
            self::$savepointsAbiertos--;
        }
    }
}
