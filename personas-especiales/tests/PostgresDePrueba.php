<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PersonasEspeciales\Migrador;

/**
 * Una base PostgreSQL limpia, con las migraciones aplicadas. Sin TEST_PG_DSN se omite la prueba
 * (como en url-routing); la CI corre con un servicio PostgreSQL. Borra las tablas del módulo: usar
 * solo una base de prueba.
 */
trait PostgresDePrueba
{
    private function conectar(): PDO
    {
        $dsn = getenv('TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Sin TEST_PG_DSN: se omite la prueba contra PostgreSQL.');
        }

        $pdo = new PDO($dsn, getenv('TEST_PG_USER') ?: null, getenv('TEST_PG_PASSWORD') ?: null);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('DROP TABLE IF EXISTS exclusion_evento, exclusion, schema_migraciones CASCADE');
        $pdo->exec('DROP FUNCTION IF EXISTS exclusion_evento_solo_agrega() CASCADE');
        (new Migrador($pdo, __DIR__ . '/../database/migraciones'))->aplicar();

        return $pdo;
    }
}
