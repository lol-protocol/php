<?php

declare(strict_types=1);

// Aplica las migraciones pendientes de database/migraciones/ a PostgreSQL.
//   PE_DSN="pgsql:host=127.0.0.1;dbname=pe" PE_USER=... PE_PASSWORD=... php bin/migrar.php
//
// Códigos de salida: 0 sin errores (haya o no migraciones pendientes), 1 si falló una migración o la
// conexión (un mensaje de una línea en STDERR, sin traza de pila), 2 si falta PE_DSN.

require __DIR__ . '/../vendor/autoload.php';

use PersonasEspeciales\Migrador;

$dsn = getenv('PE_DSN');
if ($dsn === false || $dsn === '') {
    fwrite(STDERR, "Falta PE_DSN (ej. pgsql:host=127.0.0.1;dbname=pe).\n");
    exit(2);
}

try {
    $pdo = new PDO($dsn, getenv('PE_USER') ?: null, getenv('PE_PASSWORD') ?: null);
    $aplicadas = (new Migrador($pdo, __DIR__ . '/../database/migraciones'))->aplicar();
} catch (Throwable $e) {
    // Solo la primera línea del mensaje: la traza no le sirve a quien opera y los mensajes de PDO ya traen el motivo.
    $primera = strtok($e->getMessage(), "\n");
    fwrite(STDERR, 'No se pudo migrar: ' . ($primera === false ? get_class($e) : $primera) . "\n");
    exit(1);
}

if ($aplicadas === []) {
    echo "sin migraciones pendientes\n";
    exit(0);
}
foreach ($aplicadas as $nombre) {
    echo "aplicada {$nombre}\n";
}
