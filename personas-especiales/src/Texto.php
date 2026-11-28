<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use InvalidArgumentException;

/**
 * Validación de los textos que entran al módulo (ids, actor, prueba). Rechaza lo que la base no
 * puede guardar o que no tiene sentido en un identificador: UTF-8 inválido y caracteres de control
 * (un NUL, un salto de línea dentro de un id). Mejor que el error falle aquí, con un mensaje claro y
 * antes de escribir nada, que como una excepción de PostgreSQL a mitad de una operación.
 *
 * @internal
 */
final class Texto
{
    /**
     * @param bool $permiteSaltos un texto libre corto (la prueba, la razón de un rechazo) puede tener
     *                            saltos de línea y tabuladores; un identificador, no
     *
     * @throws InvalidArgumentException si está vacío, es muy largo, no es UTF-8 válido o trae control
     */
    public static function limpio(string $valor, string $campo, int $max, bool $permiteSaltos = false): string
    {
        if (!mb_check_encoding($valor, 'UTF-8')) {
            throw new InvalidArgumentException("El campo {$campo} no es UTF-8 válido.");
        }
        // Solo se quitan los espacios de verdad: el trim() de PHP también quita los NUL del final, y un NUL
        // no es un espacio sino un carácter de control que hay que rechazar, no borrar sin avisar.
        $limpio = trim($valor, " \t\n\r\x0B");
        if ($limpio === '') {
            throw new InvalidArgumentException("El campo {$campo} no puede estar vacío.");
        }
        if (mb_strlen($limpio, 'UTF-8') > $max) {
            throw new InvalidArgumentException("El campo {$campo} pasa de {$max} caracteres.");
        }
        $control = $permiteSaltos ? '/[^\P{Cc}\n\r\t]/u' : '/\p{Cc}/u';
        if (preg_match($control, $limpio) === 1) {
            throw new InvalidArgumentException("El campo {$campo} tiene caracteres de control.");
        }

        return $limpio;
    }
}
