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
     * Largos maximos de los textos libres de los formularios. Las columnas son
     * TEXT, asi que sin tope cabia cualquier cosa: un nombre o una ciudad de
     * 100.000 caracteres se guardaban sin queja.
     */
    public const MAX_NOMBRE = 120;
    public const MAX_EMAIL = 254;
    public const MAX_CIUDAD = 100;
    public const MAX_IDIOMA = 40;
    public const MAX_CONCEPTO = 200;

    /**
     * El mensaje del primer texto que se pasa de su largo maximo, o null si todos
     * entran. Cada campo es [etiqueta con articulo ("El nombre"), valor, maximo];
     * se cuentan caracteres, no bytes.
     * @param list<array{string, string, int}> $campos
     */
    public static function primerTextoLargo(array $campos): ?string
    {
        foreach ($campos as [$etiqueta, $valor, $maximo]) {
            if (mb_strlen($valor) > $maximo) {
                return "{$etiqueta} no puede superar los {$maximo} caracteres.";
            }
        }

        return null;
    }

    /** El formato del email: el <input type="email"> solo lo revisa el navegador. */
    public static function emailEsValido(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Mensaje de error para el catch de un alta que puede chocar con un
     * email duplicado (constraint UNIQUE); usado por ClienteController.
     */
    public static function mensajeDeConflicto(PDOException $e, string $entidad): string
    {
        return str_contains($e->getMessage(), 'unique')
            ? "Ya existe un {$entidad} con ese email."
            : "No se pudo crear el {$entidad}.";
    }
}
