<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;

/**
 * El dia en que se le devolvio la plata al cliente por una nota de credito.
 *
 * Devuelve la fecha ya validada y lanza una excepcion con el mensaje que ve la
 * persona si no sirve (la misma forma que Dinero::centavos()): el que la llama no
 * puede seguir con una fecha mala, y no tiene que acordarse de revisar un retorno.
 */
final class FechaDeDevolucion
{
    /**
     * @param string $texto         lo que mando el formulario
     * @param string $fechaDeLaNota el dia en que se emitio la nota (Y-m-d)
     * @param string $hoy           el dia de hoy (Y-m-d): una devolucion no puede ser futura
     * @throws InvalidArgumentException si no es una fecha real, es anterior a la nota o es futura
     */
    public static function validar(string $texto, string $fechaDeLaNota, string $hoy): string
    {
        $fecha = trim($texto);
        if (!Validacion::fechaEsValida($fecha)) {
            throw new InvalidArgumentException('La fecha de devolución no es válida.');
        }
        if ($fecha < $fechaDeLaNota) {
            throw new InvalidArgumentException("La fecha de devolución no puede ser anterior a la nota de crédito ({$fechaDeLaNota}).");
        }
        if ($fecha > $hoy) {
            throw new InvalidArgumentException('La fecha de devolución no puede ser futura.');
        }

        return $fecha;
    }
}
