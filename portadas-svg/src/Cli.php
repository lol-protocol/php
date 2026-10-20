<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Línea de comandos del generador. `bin/generar.php` solo la llama: así se puede probar sin lanzar
 * procesos.
 */
final class Cli
{
    private const USO = <<<'TXT'
        Uso:
          php bin/generar.php libro.json [más.json ...] [--salida=carpeta]
          php bin/generar.php --comprobar
          php bin/generar.php --help

        Cada libro escribe sus SVG en <salida>/<nombre del archivo>/. La salida por defecto es salida/.
        --comprobar regenera ejemplos/*.json y falla si no coincide con ejemplos/salida/.
        TXT;

    /**
     * @param list<string> $argumentos los argumentos, sin el nombre del script
     * @param callable(string): void $salida recibe lo que se imprime
     * @param callable(string): void $errores recibe los mensajes de error
     * @return int código de salida: 0 bien, 1 error al leer o generar, 2 uso incorrecto
     */
    public static function ejecutar(array $argumentos, string $raiz, callable $salida, callable $errores): int
    {
        $rutas = [];
        $carpeta = $raiz . '/salida';
        $comprobar = false;
        foreach ($argumentos as $argumento) {
            if ($argumento === '--help' || $argumento === '-h') {
                $salida(self::USO . "\n");

                return 0;
            }
            if ($argumento === '--comprobar') {
                $comprobar = true;
            } elseif (str_starts_with($argumento, '--salida=')) {
                $carpeta = substr($argumento, strlen('--salida='));
                if ($carpeta === '') {
                    return self::uso($errores, '--salida necesita una carpeta: --salida=carpeta');
                }
            } elseif (str_starts_with($argumento, '-')) {
                return self::uso($errores, "Opción desconocida: $argumento");
            } else {
                $rutas[] = $argumento;
            }
        }

        if ($comprobar && $rutas !== []) {
            return self::uso($errores, '--comprobar no admite archivos de libros.');
        }
        if (!$comprobar && $rutas === []) {
            return self::uso($errores, 'Falta el archivo JSON del libro.');
        }

        try {
            return $comprobar ? self::comprobar($raiz, $salida, $errores) : self::generar($rutas, $carpeta, $salida);
        } catch (\Throwable $error) {
            $errores('Error: ' . $error->getMessage() . "\n");

            return 1;
        }
    }

    /** @param callable(string): void $errores */
    private static function uso(callable $errores, string $motivo): int
    {
        $errores("$motivo\n\n" . self::USO . "\n");

        return 2;
    }

    /**
     * @param list<string> $rutas
     * @param callable(string): void $salida
     */
    private static function generar(array $rutas, string $carpeta, callable $salida): int
    {
        // Se leen todos antes de escribir: un libro roto o repetido no deja la salida a medias.
        $libros = [];
        foreach ($rutas as $ruta) {
            $libro = Libro::desdeArchivo($ruta);
            if (isset($libros[$libro->id])) {
                throw new \InvalidArgumentException("$ruta y {$libros[$libro->id][1]} se llaman igual ('{$libro->id}') y escribirían en la misma carpeta. Renombra uno.");
            }
            $libros[$libro->id] = [$libro, $ruta];
        }

        foreach ($libros as [$libro]) {
            $directorio = "$carpeta/{$libro->id}";
            $salida("$directorio: " . implode(', ', Generador::escribir($libro, $directorio)) . "\n");
        }

        return 0;
    }

    /**
     * @param callable(string): void $salida
     * @param callable(string): void $errores
     */
    private static function comprobar(string $raiz, callable $salida, callable $errores): int
    {
        $temporal = sys_get_temp_dir() . '/portadas-svg-' . bin2hex(random_bytes(4));
        $diferencias = [];

        try {
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
        } finally {
            if (is_dir($temporal)) {
                Archivos::borrarDirectorio($temporal);
            }
        }

        if ($diferencias !== []) {
            $errores("ejemplos/salida no coincide con el generador. Para regenerarlo:\n"
                . "  php bin/generar.php ejemplos/*.json --salida=ejemplos/salida\n"
                . "Diferencias:\n  " . implode("\n  ", $diferencias) . "\n");

            return 1;
        }
        $salida("ejemplos/salida al día.\n");

        return 0;
    }
}
