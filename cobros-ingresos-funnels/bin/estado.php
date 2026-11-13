<?php

declare(strict_types=1);

/**
 * El estado del panel en una pantalla: la base, las migraciones, las tasas de cambio y los respaldos.
 * Solo lee (no migra, no actualiza tasas, no respalda). Sirve a mano antes y despues de un despliegue y, como
 * termina con 1 cuando algo pide atencion, de cron o de monitoreo.
 *
 * Uso:
 *   php bin/estado.php
 *   php bin/estado.php --carpeta=/var/backups/cobros   donde buscar los respaldos (RESPALDOS_CARPETA, o respaldos/ del proyecto)
 *   php bin/estado.php --ayuda
 *
 * Variables: las DB_* de la app, RESPALDOS_CARPETA y PG_DUMP/PG_RESTORE (como bin/respaldar.php).
 *
 * Termina con 0 si todo esta en orden, con 1 si algo pide atencion o no se pudo comprobar, y con 2 si los
 * argumentos estan mal. La salida va por la salida estandar; solo los errores de uso van por la de errores.
 * Ver el README, "Estado del sistema".
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Estado\EstadoDelSistema;
use App\Migrador;
use App\Respaldo\RespaldoDeLaBase;

const USO = <<<'TEXTO'
Uso:
  php bin/estado.php
  php bin/estado.php --carpeta=/var/backups/cobros   donde buscar los respaldos (RESPALDOS_CARPETA, o respaldos/ del proyecto)
  php bin/estado.php --ayuda

TEXTO;

// A mano y no con getopt(): getopt ignora en silencio "--carpeta=" vacio (ver bin/respaldar.php).
$carpeta = null;
foreach (array_slice($argv, 1) as $argumento) {
    if ($argumento === '--ayuda') {
        echo USO;
        exit(0);
    }
    if (preg_match('/^--carpeta=(.+)$/s', $argumento, $m) !== 1 || $carpeta !== null) {
        fwrite(STDERR, "Argumento desconocido, repetido o sin valor: {$argumento}\n\n" . USO);
        exit(2);
    }
    $carpeta = $m[1];
}

try {
    $estado = new EstadoDelSistema(
        new Migrador(),
        RespaldoDeLaBase::desdeElEntorno($carpeta ?? Config::variable('RESPALDOS_CARPETA', dirname(__DIR__) . '/respaldos'))
    );
    $filas = $estado->comprobar();
} catch (Throwable $e) {
    echo 'No se pudo comprobar el estado: ' . $e->getMessage() . "\n";
    exit(1);
}

$ancho = max(array_map(static fn (array $fila): int => mb_strlen($fila['tema']), $filas));
foreach ($filas as $fila) {
    // mb_str_pad y no %-9s: printf rellena por bytes y la «Ó» de ATENCIÓN ocupa dos.
    echo mb_str_pad($fila['ok'] ? 'OK' : 'ATENCIÓN', 9), ' ', mb_str_pad($fila['tema'], $ancho), '  ', $fila['detalle'], "\n";
}

$enOrden = EstadoDelSistema::todoEnOrden($filas);
echo $enOrden ? "\nTodo en orden.\n" : "\nHay algo que atender.\n";
exit($enOrden ? 0 : 1);
