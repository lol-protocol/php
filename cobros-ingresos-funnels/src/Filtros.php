<?php

declare(strict_types=1);

namespace App;

/**
 * Lo que una lista lee de la URL ($_GET) para filtrarse: un texto libre (el cliente que se busca) o
 * una opcion de una lista cerrada (el estado, el alcance, el orden). Estaba escrito a mano en cada
 * pantalla -cuatro veces el texto del cliente, tres la validacion de la opcion-. El periodo tiene su
 * propia clase: FiltroDePeriodo.
 *
 * Una opcion invalida nunca da un error: cae en el valor por defecto de la lista, y la pantalla
 * avisa con opcionInexistente() (el CSV de la misma lista no avisa: no tiene donde).
 */
final class Filtros
{
    /** El texto de ?$parametro= sin los espacios de los bordes ('' si no vino). */
    public static function texto(string $parametro): string
    {
        return trim((string) ($_GET[$parametro] ?? ''));
    }

    /**
     * El valor de ?$parametro= si es una de las claves de $permitidas; si no lo es, $porDefecto, o la
     * primera clave de la lista si no se da ('' vale: es "todos" en el filtro de estado de Cobros).
     *
     * @param array<int|string, string> $permitidas clave => etiqueta (PHP vuelve entera una clave como '3')
     */
    public static function opcion(string $parametro, array $permitidas, ?string $porDefecto = null): string
    {
        $pedido = (string) ($_GET[$parametro] ?? '');

        return array_key_exists($pedido, $permitidas) ? $pedido : ($porDefecto ?? (string) array_key_first($permitidas));
    }

    /**
     * true si ?$parametro= trae algo que no es ninguna clave de $permitidas: lo que la pantalla tiene que
     * avisar. Un parametro ausente o vacio no es una opcion inexistente, es "no se pidio nada".
     *
     * @param array<int|string, string> $permitidas clave => etiqueta (PHP vuelve entera una clave como '3')
     */
    public static function opcionInexistente(string $parametro, array $permitidas): bool
    {
        $pedido = (string) ($_GET[$parametro] ?? '');

        return $pedido !== '' && !array_key_exists($pedido, $permitidas);
    }
}
