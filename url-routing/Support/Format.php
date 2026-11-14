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
    public static function fecha(?string $valor, string $locale = HostParser::IDIOMA_BASE): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $valor, $m) !== 1) {
            return $valor;
        }

        $anio = (int)$m[1];
        $mes = (int)$m[2];
        $dia = (int)$m[3];
        $meses = self::MESES[$locale] ?? self::MESES[HostParser::IDIOMA_BASE];

        // A genealogy date is often partial: the unknown part is written 00 ("1925-00-00" is only the year,
        // "1925-02-00" a month). What is not a date at all ("1925-13-05", "1925-02-30", an hour 25) is returned
        // as it came, like any other text this does not understand.
        if ($anio === 0) {
            return $valor;
        }
        if ($mes === 0 && $dia === 0) {
            $texto = (string)$anio;
        } elseif ($mes >= 1 && $mes <= 12 && $dia === 0) {
            $texto = $meses[$mes - 1] . ' ' . $anio;
        } elseif (checkdate($mes, $dia, $anio)) {
            $texto = $dia . ' ' . $meses[$mes - 1] . ' ' . $anio;
        } else {
            return $valor;
        }

        if (!isset($m[4])) {
            return $texto;
        }

        return (int)$m[4] <= 23 && (int)$m[5] <= 59 ? "{$texto}, {$m[4]}:{$m[5]}" : $valor;
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
        $cantidad = (int)$centavos;
        $monto = number_format(abs($cantidad) / 100, 2, '.', ',');

        return ($cantidad < 0 ? '-' : '') . '$' . $monto . ' ' . trim($moneda);
    }
}
