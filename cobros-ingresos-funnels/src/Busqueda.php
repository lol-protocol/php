<?php

declare(strict_types=1);

namespace App;

/**
 * Texto tipeado en un buscador, listo para un `ILIKE`. Un `%` o un `_` del texto es un caracter como
 * cualquier otro: sin escaparlos, buscar «_» (o «%») traia a todos los clientes, y «a_b» encontraba «axb».
 *
 * Se escapa con la barra invertida, que en PostgreSQL es el escape por omision de LIKE e ILIKE. No se
 * pone `ESCAPE '\'` en la consulta a proposito: PDO lee esa barra como si escapara la comilla y deja
 * sin sustituir los parametros que vienen despues.
 */
final class Busqueda
{
    /** El patron «contiene $texto»: `%texto%` con los comodines del propio texto escapados. */
    public static function contiene(string $texto): string
    {
        return '%' . addcslashes($texto, '\\%_') . '%';
    }

    /**
     * El pedazo de WHERE de un buscador de texto y sus parametros, para que los listados no repitan la
     * condicion ni el escape: ['', []] si no se busco nada, y si no
     * [$conector . '(col1 ILIKE :param OR col2 ILIKE :param)', [':param' => '%texto%']].
     *
     * @param list<string> $columnas con su alias, p. ej. ['c.nombre', 'c.email']: las pone el codigo, nunca la persona
     * @param string $parametro sin los dos puntos
     * @param string $conector lo que va delante, con sus espacios: ' AND ' o ' WHERE '
     * @return array{0: string, 1: array<string, string>}
     */
    public static function filtro(?string $texto, array $columnas, string $parametro, string $conector): array
    {
        if ($texto === null || $texto === '') {
            return ['', []];
        }

        $condiciones = array_map(static fn (string $columna): string => "{$columna} ILIKE :{$parametro}", $columnas);

        return [$conector . '(' . implode(' OR ', $condiciones) . ')', [":{$parametro}" => self::contiene($texto)]];
    }
}
