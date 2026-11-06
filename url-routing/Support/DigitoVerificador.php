<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The check digit of an id (the Damm algorithm): the last digit of an id is computed from the ones before it, so
 * an id that was mistyped or copied wrong is told apart from one that is only absent, with no query. Damm catches
 * every single-digit error and every swap of two adjacent digits, which Luhn does not (09 and 90).
 *
 * It is opt-in: with IDS_DIGITO_VERIFICADOR off (the default) ids are plain numbers, as they have always been, and
 * nothing here runs. Turning it on is a decision about every id in the database, not a switch: each id must already
 * end in its check digit (complete() makes one; bin/id.php does it from the command line), or it answers 404.
 */
final class DigitoVerificador
{
    /** Damm's quasigroup: row = the running digit, column = the next one. A valid number ends on 0. */
    private const TABLA = [
        [0, 3, 1, 7, 5, 9, 8, 6, 4, 2],
        [7, 0, 9, 2, 1, 5, 4, 8, 6, 3],
        [4, 2, 0, 6, 8, 7, 1, 3, 5, 9],
        [1, 7, 5, 0, 9, 8, 3, 4, 2, 6],
        [6, 1, 2, 3, 0, 4, 5, 9, 7, 8],
        [3, 6, 7, 4, 2, 0, 9, 5, 8, 1],
        [5, 8, 6, 9, 7, 2, 0, 1, 3, 4],
        [8, 9, 4, 5, 3, 6, 2, 0, 1, 7],
        [9, 4, 3, 8, 6, 1, 7, 2, 0, 5],
        [2, 5, 8, 1, 4, 3, 6, 7, 9, 0],
    ];

    /** Whether ids carry a check digit on this deployment (IDS_DIGITO_VERIFICADOR in .env; off by default). */
    public static function activo(): bool
    {
        return Config::getBool('IDS_DIGITO_VERIFICADOR');
    }

    /** The digit that, written after $digitos, makes a valid id. @param numeric-string $digitos */
    public static function calcular(string $digitos): int
    {
        $intermedio = self::recorrer($digitos);
        foreach (self::TABLA[$intermedio] as $candidato => $resultado) {
            if ($resultado === 0) {
                return $candidato;
            }
        }

        throw new \LogicException('Damm table row without a 0');
    }

    /** $digitos followed by its check digit. @param numeric-string $digitos */
    public static function completar(string $digitos): string
    {
        return $digitos . self::calcular($digitos);
    }

    /** Whether $id (all digits, check digit last) is valid. */
    public static function esValido(string $id): bool
    {
        return strlen($id) >= 2 && ctype_digit($id) && self::recorrer($id) === 0;
    }

    private static function recorrer(string $digitos): int
    {
        if ($digitos === '' || !ctype_digit($digitos)) {
            throw new \InvalidArgumentException('Only digits have a check digit');
        }

        $intermedio = 0;
        foreach (str_split($digitos) as $digito) {
            $intermedio = self::TABLA[$intermedio][(int)$digito];
        }

        return $intermedio;
    }
}
