#!/usr/bin/env php
<?php

/**
 * Borra los marcalibros que ya cumplieron sus días de conservación, los archivos
 * que quedaron sueltos y los registros de intentos vencidos.
 *
 *     php bin/limpiar.php
 *
 * Conviene ejecutarlo una vez al día (cron). Usa las mismas variables de entorno
 * que la aplicación (MARCALIBROS_STORAGE, MARCALIBROS_RETENTION_DAYS).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use MarcalibrosApp\BookmarkStore;
use MarcalibrosApp\Config;
use MarcalibrosApp\RateLimiter;

try {
    $config = Config::fromEnvironment(dirname(__DIR__), getenv() ?: []);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'Configuración inválida: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

$now = time();
$store = new BookmarkStore($config->storageDirectory . '/marcalibros');
$limiter = new RateLimiter($config->storageDirectory . '/intentos');

// Con MARCALIBROS_RETENTION_DAYS=0 (lo normal) los marcalibros no caducan: solo se limpian los restos
// de subidas interrumpidas y los intentos.
if ($config->retentionDays > 0) {
    $bookmarks = $store->purgeOlderThan($config->retentionDays * 86400, $now);
} else {
    $store->purgeStray($now);
    $bookmarks = 0;
}
$attempts = $limiter->purge(3600, $now);

echo "Marcalibros borrados: $bookmarks\n";
echo "Registros de intentos borrados: $attempts\n";
