<?php

declare(strict_types=1);

namespace Marcalibros;

/**
 * Escribe un archivo de una sola vez: primero en un temporal de la misma carpeta y al
 * final lo renombra, así nadie lee un archivo a medio escribir.
 */
final class AtomicFile
{
    /** Prefijo de los temporales; `BookmarkStore::purgeStray` borra los que queden sueltos. */
    public const TEMP_PREFIX = '.tmp-';

    /** Devuelve false (sin dejar el temporal) si no pudo escribir, dar permisos o renombrar. */
    public static function write(string $path, string $bytes, int $mode = 0644): bool
    {
        $temp = tempnam(dirname($path), self::TEMP_PREFIX);
        if ($temp === false) {
            return false;
        }
        if (file_put_contents($temp, $bytes) === false || !chmod($temp, $mode) || !rename($temp, $path)) {
            @unlink($temp);
            return false;
        }
        return true;
    }
}
