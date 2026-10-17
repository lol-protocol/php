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
