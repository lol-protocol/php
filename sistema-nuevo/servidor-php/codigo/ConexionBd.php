<?php

declare(strict_types=1);

/**
 * Conexión PDO a PostgreSQL, compartida por el backend en vivo (AlmacenDatos)
 * y el cargador de datos (datos/generador/cargar-postgres*.php). Sin SQLite:
 * esto es un servidor PostgreSQL real (ver README, "Base de datos").
 *
 * Credenciales por variables de entorno, con default de desarrollo local para
 * que el sistema funcione sin configurar nada (igual que el resto del demo).
 */
final class ConexionBd
{
    private static ?PDO $pdo = null;

    public static function obtener(): PDO
    {
        if (self::$pdo === null) {
            $host = getenv('BACKOFFICE_BD_HOST') ?: 'localhost';
            $puerto = getenv('BACKOFFICE_BD_PUERTO') ?: '5432';
            $nombre = getenv('BACKOFFICE_BD_NOMBRE') ?: 'backoffice';
            $usuario = getenv('BACKOFFICE_BD_USUARIO') ?: 'backoffice_app';
            $clave = getenv('BACKOFFICE_BD_CLAVE') ?: 'backoffice_dev_2026';

            self::$pdo = new PDO(
                "pgsql:host=$host;port=$puerto;dbname=$nombre",
                $usuario,
                $clave,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        }
        return self::$pdo;
    }
}
