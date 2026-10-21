<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Operaciones de archivos que usan el CLI y las pruebas. */
final class Archivos
{
    /** Borra una carpeta con todo lo que tiene dentro. */
    public static function borrarDirectorio(string $directorio): void
    {
        foreach (new \FilesystemIterator($directorio) as $elemento) {
            if ($elemento->isDir() && !$elemento->isLink()) {
                self::borrarDirectorio($elemento->getPathname());
            } else {
                unlink($elemento->getPathname());
            }
        }
        rmdir($directorio);
    }
}
