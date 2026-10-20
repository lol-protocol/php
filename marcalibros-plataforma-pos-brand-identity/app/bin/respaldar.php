#!/usr/bin/env php
<?php

/**
 * Hace una copia de seguridad de los marcalibros creados y borra las copias más viejas.
 *
 *     php bin/respaldar.php
 *
 * Conviene ejecutarlo una vez al día (cron), mejor hacia una carpeta de otro disco
 * (MARCALIBROS_BACKUP_DIR). Usa las mismas variables de entorno que la aplicación, más
 * MARCALIBROS_BACKUP_DIR (por defecto, almacenamiento/respaldos) y MARCALIBROS_BACKUP_KEEP
 * (cuántas copias se conservan, 7 por defecto).
 *
 * Termina con código 0 si hizo la copia o no había nada que copiar, y con 1 si falló.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use MarcalibrosApp\Backup;
use MarcalibrosApp\Config;

try {
    $config = Config::fromEnvironment(dirname(__DIR__), getenv() ?: []);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'Configuración inválida: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

$backup = new Backup(
    $config->storageDirectory . '/marcalibros',
    $config->backupDirectory ?? $config->storageDirectory . '/respaldos',
    $config->backupKeep,
);

try {
    $result = $backup->create(time());
    if ($result === null) {
        echo "No hay marcalibros que copiar.\n";
        exit(0);
    }
    $deleted = $backup->rotate();
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo hacer la copia: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

printf("Copia: %s (%d marcalibros, %.1f MB)\n", $result['file'], $result['bookmarks'], $result['bytes'] / 1048576);
echo "Copias antiguas borradas: $deleted\n";
