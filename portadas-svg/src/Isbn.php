<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Comprobación del dígito de control de un ISBN-10 o un ISBN-13. */
final class Isbn
{
    /** Si el texto es un ISBN con el dígito de control bien. Admite guiones y espacios, y un `ISBN` delante. */
    public static function valido(string $texto): bool
    {
        $limpio = strtoupper((string) preg_replace('/^\s*ISBN(?:-1[03])?:?\s*|[\s-]/i', '', $texto));

        return match (strlen($limpio)) {
            13 => preg_match('/^97[89]\d{10}$/', $limpio) === 1 && self::control13($limpio),
            10 => preg_match('/^\d{9}[\dX]$/', $limpio) === 1 && self::control10($limpio),
            default => false,
        };
    }

    private static function control13(string $isbn): bool
    {
        $suma = 0;
        for ($i = 0; $i < 13; $i++) {
            $suma += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $suma % 10 === 0;
    }

    private static function control10(string $isbn): bool
    {
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += ($isbn[$i] === 'X' ? 10 : (int) $isbn[$i]) * (10 - $i);
        }

        return $suma % 11 === 0;
    }
}
