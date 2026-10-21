<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Colores en formato #rrggbb: validación, mezcla y claridad. */
final class Color
{
    public static function valido(string $hex): bool
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $hex) === 1;
    }

    /** @return array{0: int, 1: int, 2: int} */
    public static function rgb(string $hex): array
    {
        if (!self::valido($hex)) {
            throw new \InvalidArgumentException("Color no válido: '$hex'. Usa #rrggbb.");
        }

        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    /** Mezcla `desde` y `hacia`: con proporción 0 queda `desde`, con 1 queda `hacia`. */
    public static function mezclar(string $desde, string $hacia, float $proporcion): string
    {
        [$r1, $g1, $b1] = self::rgb($desde);
        [$r2, $g2, $b2] = self::rgb($hacia);
        $mezcla = static fn (int $a, int $b): int => (int) round($a + ($b - $a) * $proporcion);

        return sprintf('#%02x%02x%02x', $mezcla($r1, $r2), $mezcla($g1, $g2), $mezcla($b1, $b2));
    }

    /** Luma perceptual por encima de la mitad: el color se lee como claro. */
    public static function esClaro(string $hex): bool
    {
        [$r, $g, $b] = self::rgb($hex);

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 > 0.5;
    }
}
