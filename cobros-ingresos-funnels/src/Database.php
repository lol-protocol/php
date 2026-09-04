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
}
