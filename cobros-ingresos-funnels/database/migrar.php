<?php

declare(strict_types=1);

/**
 * Aplica las migraciones pendientes de database/migraciones/, en orden.
 *
 * Uso:
 *   php database/migrar.php              en cada despliegue; si la base esta al dia no hace nada
 *   php database/migrar.php --baseline   UNA sola vez, en una base creada antes de las
 *                                        migraciones con el viejo database/schema.sql: marca la
 *                                        001 como aplicada (sin correrla) y aplica las demas
 *
 * Usa las mismas variables DB_* que la app. No toca datos: para regenerar los
 * datos de ejemplo en desarrollo esta database/seed.php.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Migrador;

try {
    $migrador = new Migrador();

    if (in_array('--baseline', $argv, true)) {
        $migrador->marcarComoAplicada(Migrador::INICIAL);
        echo 'Marcada como aplicada (sin ejecutarla): ' . Migrador::INICIAL . "\n";
    }

    $aplicadas = $migrador->aplicar();
    echo $aplicadas === []
        ? "La base ya estaba al dia.\n"
        : "Migraciones aplicadas:\n  " . implode("\n  ", $aplicadas) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");

    // 42P07 = la tabla ya existe: una base creada antes de las migraciones.
    $previa = $e->getPrevious();
    if ($previa instanceof PDOException && $previa->getCode() === '42P07' && str_contains($e->getMessage(), Migrador::INICIAL)) {
        fwrite(STDERR, "Si la base se creo con el viejo database/schema.sql, corre una sola vez:\n"
            . "  php database/migrar.php --baseline\n");
    }
    exit(1);
}
