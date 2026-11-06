<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Números escritos a mano en una celda de una hoja de cálculo. */
final class Numeros
{
    /**
     * Un número decimal con punto o con coma (`5.6` o `5,6`), o null si el texto no es un número. Sin signo, a menos
     * que se pida (para un ángulo). No admite miles (`1.000`), notación científica ni espacios.
     */
    public static function decimal(string $valor, bool $conSigno = false): ?float
    {
        if (preg_match($conSigno ? '/^-?\d+(?:[.,]\d+)?$/' : '/^\d+(?:[.,]\d+)?$/', $valor) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $valor);
    }
}
