<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '5432';
            $nombre = getenv('DB_NAME') ?: 'cobros_ingresos_funnels';
            $usuario = getenv('DB_USER') ?: 'cobros_app';
            $clave = getenv('DB_PASSWORD') ?: 'cobros_app_dev';

            $dsn = "pgsql:host={$host};port={$port};dbname={$nombre}";
            $pdo = new PDO($dsn, $usuario, $clave);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$connection = $pdo;
        }

        return self::$connection;
    }

    /**
     * Corre $operacion dentro de una transaccion: o quedan todas sus
     * escrituras o ninguna. Para los flujos que escriben mas de una fila
     * relacionada (ej. anular una boleta y emitir su nota de credito: si la
     * nota fallara despues del UPDATE, la boleta quedaria anulada con sus
     * pagos sin respaldo, y la guarda de idempotencia impediria reintentar).
     * La excepcion se vuelve a lanzar tras el rollback, para que la maneje
     * el ErrorHandler global.
     */
    public static function transaccion(callable $operacion): mixed
    {
        $db = self::connection();
        $db->beginTransaction();
        try {
            $resultado = $operacion();
            $db->commit();
            return $resultado;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
