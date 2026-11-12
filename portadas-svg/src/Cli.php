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
          php bin/generar.php --lista=libros.csv [--lista=otra.csv ...] [libro.json ...] [--omitir-errores] [--trazos] [--extra=carpeta]
                              [--incremental] [--manifiesto] [--json] [--indice] [--salida=carpeta]
          php bin/generar.php --comprobar
          php bin/generar.php --help

        Cada libro escribe sus SVG en <salida>/<nombre del archivo>/ (o <salida>/<id>/ si viene de una
        lista CSV). La salida por defecto es salida/.
        --lista lee una fila por libro de un CSV en UTF-8 (ver README.md); si alguna fila tiene errores, los
        da todos juntos y no escribe nada. Con --omitir-errores, salta las filas malas, las avisa y escribe
        el resto.
        --trazos escribe el texto como contornos en vez de con la fuente incrustada: se ve igual en cualquier
        visor, pero el texto ya no se puede seleccionar ni buscar.
        --extra=carpeta usa los .json de esa carpeta (ver herramientas/glifos-extra.py) para las letras que las
        fuentes del proyecto no tienen; solo se dibujan con --trazos. Sin la opción, se buscan en fuentes/extra/.
        --incremental no vuelve a escribir los libros que no cambiaron desde la última vez (sus datos, el modo y el
        generador); sirve para editar una lista de cientos de libros por tandas.
        --manifiesto escribe <salida>/manifiesto.json con cada libro, sus archivos, tamaños y avisos.
        --indice escribe <salida>/indice.svg, una hoja con la portada de cada libro en miniatura (con su título y autor).
        --json imprime ese mismo manifiesto por la salida estándar, en lugar de una línea por libro: para usar el
        generador desde otro programa. Los avisos y los errores siguen saliendo como texto por la salida de errores.
        --comprobar regenera ejemplos/*.json y falla si no coincide con ejemplos/salida/ (y con ejemplos/salida-trazos/).

        Códigos de salida: 0 bien; 1 error al escribir; 2 uso incorrecto; 3 datos no válidos (un libro o una lista).
        TXT;

    /**
     * @param list<string> $argumentos los argumentos, sin el nombre del script
     * @param callable(string): void $salida recibe lo que se imprime
     * @param callable(string): void $errores recibe los mensajes de error
     * @return int código de salida: 0 bien, 1 error al escribir, 2 uso incorrecto, 3 datos no válidos
     */
    public static function ejecutar(array $argumentos, string $raiz, callable $salida, callable $errores): int
    {
        $rutas = [];
        $listas = [];
        $carpeta = $raiz . '/salida';
        $comprobar = false;
        $omitir = false;
        $trazos = false;
        $extra = null;
        $opciones = ['incremental' => false, 'manifiesto' => false, 'json' => false, 'indice' => false];
        foreach ($argumentos as $argumento) {
            if ($argumento === '--help' || $argumento === '-h') {
                $salida(self::USO . "\n");

                return 0;
            }
            if ($argumento === '--comprobar') {
                $comprobar = true;
            } elseif ($argumento === '--omitir-errores') {
                $omitir = true;
            } elseif ($argumento === '--trazos') {
                $trazos = true;
            } elseif (in_array($argumento, ['--incremental', '--manifiesto', '--json', '--indice'], true)) {
                $opciones[substr($argumento, 2)] = true;
            } elseif (str_starts_with($argumento, '--extra=')) {
                $extra = substr($argumento, strlen('--extra='));
                if ($extra === '' || !is_dir($extra)) {
                    return self::uso($errores, "--extra necesita una carpeta que exista: --extra=carpeta (recibí «{$extra}»).");
                }
            } elseif (str_starts_with($argumento, '--salida=')) {
                $carpeta = substr($argumento, strlen('--salida='));
                if ($carpeta === '') {
                    return self::uso($errores, '--salida necesita una carpeta: --salida=carpeta');
                }
            } elseif (str_starts_with($argumento, '--lista=')) {
                $lista = substr($argumento, strlen('--lista='));
                if ($lista === '') {
                    return self::uso($errores, '--lista necesita un archivo: --lista=libros.csv');
                }
                $listas[] = $lista;
            } elseif (str_starts_with($argumento, '-')) {
                return self::uso($errores, "Opción desconocida: $argumento");
            } else {
                $rutas[] = $argumento;
            }
        }

        if ($comprobar && ($rutas !== [] || $listas !== [])) {
            return self::uso($errores, '--comprobar no admite archivos de libros ni listas.');
        }
        if (!$comprobar && $rutas === [] && $listas === []) {
            return self::uso($errores, 'Falta el archivo JSON del libro o una lista con --lista=.');
        }

        try {
            Fuentes::usarExtras($extra ?? $raiz . '/fuentes/extra');

            return $comprobar ? self::comprobar($raiz, $salida, $errores) : self::generar($rutas, $listas, $omitir, $trazos, $opciones, $carpeta, $salida, $errores);
        } catch (\InvalidArgumentException $error) {
            // Datos que no valen (un libro, una lista, una opción): se pueden corregir sin tocar el programa.
            $errores('Error: ' . $error->getMessage() . "\n");

            return 3;
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
     * @param list<string> $listas
     * @param array{incremental: bool, manifiesto: bool, json: bool, indice: bool} $opciones
     * @param callable(string): void $salida
     * @param callable(string): void $errores
     */
    private static function generar(array $rutas, array $listas, bool $omitir, bool $trazos, array $opciones, string $carpeta, callable $salida, callable $errores): int
    {
        $inicio = microtime(true);
        // Se leen todos antes de escribir: un libro roto o repetido no deja la salida a medias.
        $libros = [];
        $omitidas = [];
        $agregar = static function (Libro $libro, string $origen) use (&$libros): void {
            if (isset($libros[$libro->id])) {
                throw new \InvalidArgumentException("$origen y {$libros[$libro->id][1]} se llaman igual ('{$libro->id}') y escribirían en la misma carpeta. Renombra uno.");
            }
            $libros[$libro->id] = [$libro, $origen];
        };
        foreach ($rutas as $ruta) {
            $agregar(Libro::desdeArchivo($ruta), $ruta);
        }
        foreach ($listas as $lista) {
            foreach (Lista::leer($lista, $omitir, static function (string $mensaje) use ($errores, &$omitidas): void {
                $omitidas[] = $mensaje;
                $errores("Aviso: $mensaje\n");
            }) as $libro) {
                $agregar($libro, "$lista (id '{$libro->id}')");
            }
        }

        $avisosPorLibro = [];
        foreach ($libros as [$libro]) {
            $avisosPorLibro[$libro->id] = Generador::avisos($libro, $trazos);
            foreach ($avisosPorLibro[$libro->id] as $aviso) {
                $errores("Aviso, {$libro->id}: $aviso\n");
            }
        }

        $entradas = [];
        $escritos = 0;
        $bytes = 0;
        foreach ($libros as [$libro]) {
            $directorio = "$carpeta/{$libro->id}";
            $igual = $opciones['incremental'] && Generador::sinCambios($libro, $directorio, $trazos);
            $archivos = $igual ? array_map('basename', glob("$directorio/*.{svg,txt}", GLOB_BRACE) ?: []) : Generador::escribir($libro, $directorio, $trazos);
            $escritos += $igual ? 0 : 1;
            $tamanos = [];
            foreach ($archivos as $archivo) {
                $tamanos[$archivo] = (int) filesize("$directorio/$archivo");
                $bytes += $tamanos[$archivo];
            }
            $entradas[] = [
                'id' => $libro->id, 'titulo' => $libro->textos->titulo, 'autor' => $libro->textos->autor, 'carpeta' => $directorio,
                'estado' => $igual ? 'sin cambios' : 'escrito', 'archivos' => $tamanos, 'avisos' => $avisosPorLibro[$libro->id],
            ];
            if (!$opciones['json']) {
                $salida("$directorio: " . ($igual ? 'sin cambios' : implode(', ', $archivos)) . "\n");
            }
        }

        if ($opciones['indice']) {
            if (!is_dir($carpeta) && !mkdir($carpeta, 0777, true) && !is_dir($carpeta)) {
                throw new \RuntimeException("No se pudo crear $carpeta.");
            }
            file_put_contents("$carpeta/indice.svg", Indice::svg(array_map(static fn (array $par): Libro => $par[0], array_values($libros))));
        }

        $manifiesto = [
            'libros' => $entradas, 'total' => count($entradas), 'escritos' => $escritos, 'sin_cambios' => count($entradas) - $escritos,
            'avisos' => array_sum(array_map('count', $avisosPorLibro)), 'filas_omitidas' => $omitidas, 'bytes' => $bytes,
            'segundos' => round(microtime(true) - $inicio, 2),
        ];
        if ($opciones['manifiesto']) {
            file_put_contents("$carpeta/manifiesto.json", json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        }
        if ($opciones['json']) {
            $salida(json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        } elseif (count($entradas) > 1 || $opciones['incremental']) {
            $salida(sprintf("%d libros (%d escritos, %d sin cambios), %d avisos, %s MB, %s s\n", count($entradas), $escritos, count($entradas) - $escritos, $manifiesto['avisos'], number_format($bytes / 1048576, 1, ',', ''), number_format($manifiesto['segundos'], 1, ',', '')));
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
                // Cada ejemplo con la fuente incrustada, y con el texto a trazos si hay carpeta guardada de ese modo.
                foreach (['salida' => false, 'salida-trazos' => true] as $guardada => $trazos) {
                    $carpetaEsperada = "$raiz/ejemplos/$guardada/{$libro->id}";
                    if ($trazos && !is_dir($carpetaEsperada)) {
                        continue;
                    }
                    $generados = Generador::escribir($libro, "$temporal/$guardada/{$libro->id}", $trazos);
                    foreach ($generados as $archivo) {
                        $esperado = "$carpetaEsperada/$archivo";
                        if (!is_file($esperado) || file_get_contents($esperado) !== file_get_contents("$temporal/$guardada/{$libro->id}/$archivo")) {
                            $diferencias[] = "$guardada/{$libro->id}/$archivo";
                        }
                    }
                    foreach (glob("$carpetaEsperada/*") ?: [] as $esperado) {
                        if (!in_array(basename($esperado), $generados, true)) {
                            $diferencias[] = "$guardada/{$libro->id}/" . basename($esperado) . ' (sobra)';
                        }
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
                . "  php bin/generar.php ejemplos/*.json --trazos --salida=ejemplos/salida-trazos   (si cambió esa carpeta)\n"
                . "Diferencias:\n  " . implode("\n  ", $diferencias) . "\n");

            return 1;
        }
        $salida("ejemplos/salida al día.\n");

        return 0;
    }
}
