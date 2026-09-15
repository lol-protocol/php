<?php

declare(strict_types=1);

namespace App;

use PDOException;

/** Chequeos repetidos entre los distintos formularios de alta/edicion. */
final class Validacion
{
    /**
     * true si algun campo de texto requerido vino vacio, o si $monto (cuando
     * se pasa) no es mayor a cero. Mismo chequeo que se repetia a mano en
     * cada controller de alta/edicion.
     */
    public static function faltanCampos(array $camposTexto, ?float $monto = null): bool
    {
        foreach ($camposTexto as $valor) {
            if (trim((string) $valor) === '') {
                return true;
            }
        }
        return $monto !== null && $monto <= 0;
    }

    /**
     * Mensaje de error para el catch de un alta que puede chocar con un
     * email duplicado (constraint UNIQUE); usado por ClienteController y
     * UsuarioController, que tienen el mismo catch salvo el nombre de la
     * entidad.
     */
    public static function mensajeDeConflicto(PDOException $e, string $entidad): string
    {
        return str_contains($e->getMessage(), 'unique')
            ? "Ya existe un {$entidad} con ese email."
            : "No se pudo crear el {$entidad}.";
    }
}
