<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Pasa un texto a mayúsculas según el idioma: en turco y azerí, `i` da `İ` y `ı` da `I`, no `I` para las dos. */
final class Mayusculas
{
    public static function de(string $texto, string $idioma = 'es'): string
    {
        if (in_array(strtolower(substr($idioma, 0, 2)), ['tr', 'az'], true)) {
            $texto = strtr($texto, ['i' => 'İ', 'ı' => 'I']);
        }

        return mb_strtoupper($texto);
    }
}
