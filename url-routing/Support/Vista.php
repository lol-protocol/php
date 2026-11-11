<?php

declare(strict_types=1);

namespace App\Support;

/** Draws a view file with its data in scope. */
final class Vista
{
    /**
     * The output of $archivo with each key of $datos as a variable. A view that fails throws, and every output
     * buffer it had opened (the layout pattern: a view fills one, then includes the layout) is closed first, so
     * nothing it had drawn leaks into the page that replaces it.
     *
     * @param array<string, mixed> $datos
     */
    public static function dibujar(string $archivo, array $datos): string
    {
        $nivel = ob_get_level();
        ob_start();
        try {
            (function () use ($archivo, $datos): void {
                foreach ($datos as $clave => $valor) {
                    // $archivo and $datos are this scope's own variables; letting a data key overwrite them
                    // would change which file gets included.
                    if ($clave === 'archivo' || $clave === 'datos') {
                        continue;
                    }
                    ${$clave} = $valor;
                }
                include $archivo;
            })();

            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivel) {
                ob_end_clean();
            }
            throw $e;
        }
    }
}
