<?php

declare(strict_types=1);

namespace App\Support;

/** Display formatting for values coming out of the database. */
final class Format
{
    private const MESES = [
        'spa' => ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
        'eng' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    ];

    /**
     * "1925-02-14" -> "14 feb 1925"; timestamps keep hours and minutes.
     * Anything unparseable is returned as-is rather than guessed at.
     */
    public static function fecha(?string $valor, string $locale = 'spa'): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $valor, $m) !== 1) {
            return $valor;
        }

        $meses = self::MESES[$locale] ?? self::MESES['spa'];
        $texto = (int)$m[3] . ' ' . $meses[(int)$m[2] - 1] . ' ' . $m[1];

        return isset($m[4]) ? "{$texto}, {$m[4]}:{$m[5]}" : $texto;
    }

    /** "1868-05-12", "1941-10-05" -> "1868–1941"; an open end stays open. */
    public static function vida(?string $nacimiento, ?string $defuncion): string
    {
        $anio = static fn(?string $f) => $f !== null && $f !== '' ? substr($f, 0, 4) : '';
        $desde = $anio($nacimiento);
        $hasta = $anio($defuncion);

        return $desde === '' && $hasta === '' ? '' : "{$desde}–{$hasta}";
    }

    /** Integer cents -> "$1,234.50 MXN". */
    public static function dinero(int|string|null $centavos, string $moneda = 'MXN'): string
    {
        $monto = number_format(((int)$centavos) / 100, 2, '.', ',');
        return '$' . $monto . ' ' . trim($moneda);
    }
}
