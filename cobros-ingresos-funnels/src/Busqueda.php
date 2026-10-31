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
}
