<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use RuntimeException;

/** Lo que comparten los programas de `bin/`, para no copiarlo en cada uno. */
final class Cli
{
    /**
     * Carga la lista de exclusión que se pasó con `--excluir=`. Si no se puede leer, o una línea está mal escrita,
     * lo dice por la salida de errores y termina con código 2. Si la lista no tiene ninguna entrada (todo comentado,
     * o vacía) avisa: casi seguro es un error y no se estaría excluyendo a nadie.
     */
    public static function exclusiones(string $ruta): Exclusiones
    {
        try {
            $lista = Exclusiones::desdeArchivo($ruta);
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(2);
        }
        if ($lista->estaVacia()) {
            fwrite(STDERR, "Aviso: la lista de exclusión $ruta no tiene ninguna entrada, así que no se excluye a nadie.\n");
        }
        return $lista;
    }
}
