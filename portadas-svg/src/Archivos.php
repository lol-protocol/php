<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Operaciones de archivos que usan el CLI y las pruebas. */
final class Archivos
{
    /** Borra una carpeta con todo lo que tiene dentro. */
    public static function borrarDirectorio(string $directorio): void
    {
        foreach (scandir($directorio) ?: [] as $nombre) {
            if ($nombre === '.' || $nombre === '..') {
                continue;
            }
            $ruta = "$directorio/$nombre";
            if (is_dir($ruta) && !is_link($ruta)) {
                self::borrarDirectorio($ruta);
            } else {
                unlink($ruta);
            }
        }
        rmdir($directorio);
    }
}
