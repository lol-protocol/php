<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;

/**
 * Montos de dinero sin el ruido de la coma flotante. La base los guarda
 * NUMERIC(14, 2), exactos; en PHP viajan como float, y sumar o restar floats
 * arrastra error (0.1 + 0.2 da 0.30000000000000004). Para compararlos se pasan
 * a centavos enteros: ahi la suma y la resta de montos de dos decimales es
 * exacta.
 */
final class Dinero
{
    /**
     * Un centavo. Es el monto minimo que se puede facturar o cobrar y, a la vez,
     * la regla deliberada de que **un centavo o menos cuenta como cero**: un
     * residuo de redondeo no deja una boleta pendiente para siempre. Una sola
     * constante para el PHP (cuentaComoCero()), el SQL (BoletaRepository::ESTADO_SQL
     * y la cartera por antiguedad) y los campos de monto de los formularios.
     */
    public const UN_CENTAVO = 0.01;

    /**
     * true si el monto es de un centavo o menos (un saldo en cero o negativo
     * tambien). Se compara en centavos enteros, como el resto de la clase.
     */
    public static function cuentaComoCero(float $monto): bool
    {
        return self::centavos($monto) <= self::centavos(self::UN_CENTAVO);
    }

    /**
     * El monto en centavos enteros: 602.85 -> 60285. Redondea al centavo mas
     * cercano (no trunca: 0.57 * 100 da 56.99999999999999), asi que tambien
     * absorbe el ruido de una suma o resta de floats. Acepta el texto que
     * devuelve la base ('602.85').
     *
     * Un monto que no es finito es un error de quien llama: convertirlo a 0
     * lo dejaria pasar cualquier comparacion.
     *
     * @throws InvalidArgumentException si el monto es INF o NAN
     */
    public static function centavos(float|int|string $monto): int
    {
        $valor = (float) $monto;
        if (!is_finite($valor)) {
            throw new InvalidArgumentException('El monto no es un número finito.');
        }

        return (int) round($valor * 100);
    }
}
