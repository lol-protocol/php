<?php

declare(strict_types=1);

/**
 * Genera las piezas SVG de cada libro (un JSON, ver README.md).
 *
 *   php bin/generar.php ejemplos/platon-apologia.json [más.json] [--salida=carpeta]
 *   php bin/generar.php --comprobar
 *
 * Por defecto escribe en salida/<id>/. --comprobar regenera ejemplos/*.json en una carpeta temporal
 * y falla si no coincide con ejemplos/salida/, que está en el repositorio.
 */

require __DIR__ . '/../src/autocarga.php';

use PortadasSvg\Generador;
use PortadasSvg\Libro;

$raiz = dirname(__DIR__);
$argumentos = array_slice($argv, 1);

try {
    if (in_array('--comprobar', $argumentos, true)) {
        exit(comprobar($raiz));
    }

    $salida = $raiz . '/salida';
    $rutas = [];
    foreach ($argumentos as $argumento) {
        if (str_starts_with($argumento, '--salida=')) {
            $salida = substr($argumento, strlen('--salida='));
        } else {
            $rutas[] = $argumento;
        }
    }
    if ($rutas === []) {
        fwrite(STDERR, "Uso: php bin/generar.php libro.json [--salida=carpeta]\n       php bin/generar.php --comprobar\n");
        exit(2);
    }

    foreach ($rutas as $ruta) {
        $libro = Libro::desdeArchivo($ruta);
        $directorio = "$salida/{$libro->id}";
        echo "$directorio: " . implode(', ', Generador::escribir($libro, $directorio)) . "\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n");
    exit(1);
}

function comprobar(string $raiz): int
{
    $temporal = sys_get_temp_dir() . '/portadas-svg-' . bin2hex(random_bytes(4));
    $diferencias = [];

    foreach (glob("$raiz/ejemplos/*.json") ?: [] as $ruta) {
        $libro = Libro::desdeArchivo($ruta);
        $generados = Generador::escribir($libro, "$temporal/{$libro->id}");
        $carpetaEsperada = "$raiz/ejemplos/salida/{$libro->id}";

        foreach ($generados as $archivo) {
            $esperado = "$carpetaEsperada/$archivo";
            if (!is_file($esperado) || file_get_contents($esperado) !== file_get_contents("$temporal/{$libro->id}/$archivo")) {
                $diferencias[] = "{$libro->id}/$archivo";
            }
        }
        foreach (glob("$carpetaEsperada/*") ?: [] as $esperado) {
            if (!in_array(basename($esperado), $generados, true)) {
                $diferencias[] = "{$libro->id}/" . basename($esperado) . ' (sobra)';
            }
        }
    }

    borrarDirectorio($temporal);
    if ($diferencias !== []) {
        fwrite(STDERR, "ejemplos/salida no coincide con el generador. Para regenerarlo:\n"
            . "  php bin/generar.php ejemplos/*.json --salida=ejemplos/salida\n"
            . "Diferencias:\n  " . implode("\n  ", $diferencias) . "\n");

        return 1;
    }
    echo "ejemplos/salida al día.\n";

    return 0;
}

function borrarDirectorio(string $directorio): void
{
    foreach (glob("$directorio/*") ?: [] as $ruta) {
        if (is_dir($ruta)) {
            borrarDirectorio($ruta);
        } else {
            unlink($ruta);
        }
    }
    rmdir($directorio);
}
