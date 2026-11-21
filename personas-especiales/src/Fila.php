<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use UnexpectedValueException;

/**
 * Lectura comprobada de una fila que devuelve la base. PDO entrega `mixed`; en vez de convertir
 * a texto a ciegas (un tipo inesperado se volvería «» sin avisar), se exige lo que el esquema promete.
 *
 * @internal
 */
final class Fila
{
    /** @param array<array-key, mixed> $fila */
    public static function texto(array $fila, string $columna): string
    {
        $valor = $fila[$columna] ?? null;
        if (!is_string($valor)) {
            throw new UnexpectedValueException("La columna {$columna} debía ser texto y no lo es.");
        }

        return $valor;
    }

    /** @param array<array-key, mixed> $fila */
    public static function textoONulo(array $fila, string $columna): ?string
    {
        $valor = $fila[$columna] ?? null;

        return $valor === null ? null : self::texto($fila, $columna);
    }

    /**
     * Lo que devuelve fetch(): una fila, o false si no hay más.
     *
     * @return array<array-key, mixed>|null
     */
    public static function deFetch(mixed $fetch): ?array
    {
        if ($fetch === false) {
            return null;
        }
        if (!is_array($fetch)) {
            throw new UnexpectedValueException('La base devolvió algo que no es una fila.');
        }

        return $fetch;
    }
}
