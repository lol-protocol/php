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
        return $monto !== null && !self::montoEnRango($monto);
    }

    /** Tope de NUMERIC(14, 2): por encima la base rechaza el INSERT con un 500. */
    public const MONTO_MAXIMO = 999_999_999_999.99;

    /**
     * La base guarda montos NUMERIC(14, 2) con CHECK (monto > 0): un 0.004
     * se redondea a 0.00 y viola el CHECK, y un 1e20 o INF desborda la
     * columna. Ambos terminaban en 500 en vez de en un error de formulario.
     */
    public static function montoEnRango(float $monto): bool
    {
        return is_finite($monto) && round($monto, 2) >= 0.01 && $monto <= self::MONTO_MAXIMO;
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
