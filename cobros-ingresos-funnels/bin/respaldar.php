<?php

declare(strict_types=1);

/**
 * Hace un respaldo de la base con pg_dump y borra los viejos. Pensado para cron, una vez por dia.
 *
 * Uso:
 *   php bin/respaldar.php                        respalda en RESPALDOS_CARPETA (por defecto, respaldos/ del proyecto)
 *   php bin/respaldar.php --carpeta=/var/backups/cobros
 *   php bin/respaldar.php --dias=30              cuantos dias conservar los respaldos (14 por defecto)
 *   php bin/respaldar.php --ayuda
 *
 * Variables: las DB_* de la app, y ademas RESPALDOS_CARPETA, PG_DUMP y PG_RESTORE (la ruta de esos
 * dos programas, si no estan en el PATH).
 *
 * Lo normal sale por la salida estandar; lo que pide atencion (un respaldo mucho mas chico que el
 * anterior) o un error, por la de errores, que es la que cron manda por mail. Termina con 0 si el
 * respaldo salio y se pudo leer, con 1 si no (no queda un archivo a medias, y no se borra ningun
 * respaldo anterior) y con 2 si los argumentos estan mal. Ver el README, "Respaldos".
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Respaldo\RespaldoDeLaBase;
use App\Respaldo\RespaldoFallido;

const USO = <<<'TEXTO'
Uso:
  php bin/respaldar.php                        respalda en RESPALDOS_CARPETA (por defecto, respaldos/ del proyecto)
  php bin/respaldar.php --carpeta=/var/backups/cobros
  php bin/respaldar.php --dias=30              cuantos dias conservar los respaldos (14 por defecto)
  php bin/respaldar.php --ayuda

TEXTO;

// A mano y no con getopt(): getopt ignora en silencio "--carpeta=" (vacio, p. ej. de una variable de shell sin
// definir) y el respaldo terminaria en la carpeta por defecto sin que nadie lo note.
$valores = ['carpeta' => null, 'dias' => null];
foreach (array_slice($argv, 1) as $argumento) {
    if ($argumento === '--ayuda') {
        echo USO;
        exit(0);
    }
    if (preg_match('/^--(carpeta|dias)=(.+)$/s', $argumento, $m) !== 1 || $valores[$m[1]] !== null) {
        fwrite(STDERR, "Argumento desconocido, repetido o sin valor: {$argumento}\n\n" . USO);
        exit(2);
    }
    $valores[$m[1]] = $m[2];
}

$carpeta = $valores['carpeta'] ?? Config::variable('RESPALDOS_CARPETA', dirname(__DIR__) . '/respaldos');
$dias = $valores['dias'] ?? (string) RespaldoDeLaBase::DIAS_POR_DEFECTO;
if (preg_match('/^[1-9]\d{0,3}$/', $dias) !== 1) {
    fwrite(STDERR, "--dias es un número de días de 1 en adelante.\n\n" . USO);
    exit(2);
}

try {
    ['DB_NAME' => $nombre, 'DB_USER' => $usuario, 'DB_PASSWORD' => $clave] = Config::variablesObligatorias([
        'DB_NAME' => 'cobros_ingresos_funnels',
        'DB_USER' => 'cobros_app',
        'DB_PASSWORD' => 'cobros_app_dev',
    ]);

    $resultado = (new RespaldoDeLaBase(
        [
            'host' => Config::variable('DB_HOST', '127.0.0.1'),
            'port' => Config::variable('DB_PORT', '5432'),
            'nombre' => $nombre,
            'usuario' => $usuario,
            'clave' => $clave,
        ],
        $carpeta,
        (int) $dias,
        Config::variable('PG_DUMP', 'pg_dump'),
        Config::variable('PG_RESTORE', 'pg_restore'),
    ))->respaldar();
} catch (RespaldoFallido | InvalidArgumentException | RuntimeException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

printf("Respaldo hecho: %s (%s)\n", $resultado['archivo'], RespaldoDeLaBase::tamanio($resultado['bytes']));
if ($resultado['eliminados'] !== []) {
    printf("Borrados por viejos (más de %d días): %s\n", (int) $dias, implode(', ', $resultado['eliminados']));
}
if ($resultado['advertencias'] !== []) {
    fwrite(STDERR, "Pide atención:\n  " . implode("\n  ", $resultado['advertencias']) . "\n");
}
