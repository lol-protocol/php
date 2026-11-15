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
        return self::milesimas($monto) <= self::milesimas(self::UN_CENTAVO);
    }

    /**
     * El monto de un texto de formulario: solo digitos con punto decimal opcional ("1500.50", "100"). Un
     * formulario del navegador siempre lo manda asi (type="number", step 0.01); un script puede mandar
     * "1.500,50", y (float) lo lee como 1.5 sin avisar, porque corta en la primera coma: una boleta 1.000
     * veces menor. Por eso no se acepta nada que no sea exactamente un monto: ni coma decimal, ni
     * separador de miles, ni signo, ni exponente.
     *
     * Tres decimales justos tampoco: "1.500" tiene la forma de un grupo de miles, y quien usa el punto de
     * miles escribe asi el mil quinientos; leido como 1.5 es el mismo error de mil veces que la coma
     * decimal. Se rechaza en vez de adivinar. Con uno o dos decimales, o con cuatro o mas, no hay
     * ambiguedad y se lee (quien llama redondea al centavo: ver Peticion::monto()).
     *
     * @throws InvalidArgumentException si el texto no es un monto
     */
    public static function deTexto(string $texto, int $decimales = 2): float
    {
        $texto = trim($texto);
        // En una moneda de tres decimales (dinar kuwaiti, rial omani...) tres decimales son lo normal y no se
        // confunden con un punto de miles; en las demas, tres justos se rechazan (ver arriba).
        $patron = $decimales >= 3 ? '/^\d+(\.\d+)?$/D' : '/^\d+(\.\d{1,2}|\.\d{4,})?$/D';
        if (preg_match($patron, $texto) !== 1) {
            $permitidos = $decimales >= 3 ? 'con hasta tres decimales' : 'con uno o dos decimales';
            throw new InvalidArgumentException("«{$texto}» no es un monto: use solo dígitos y punto decimal, {$permitidos}.");
        }

        return (float) $texto;
    }

    /**
     * El monto en milesimas enteras: 602.85 -> 602850, 1.505 -> 1505. Es lo que usan las reglas de dinero desde la
     * migracion 019 (montos de hasta tres decimales): una moneda de dos decimales solo tiene ceros en la tercera, asi
     * que comparar en milesimas da lo mismo que comparar en centavos para ella, y para el dinar kuwaiti no pierde el
     * fils. Redondea a la milesima mas cercana, como centavos() al centavo.
     *
     * @throws InvalidArgumentException si el monto es INF o NAN
     */
    public static function milesimas(float|int|string $monto): int
    {
        $valor = (float) $monto;
        if (!is_finite($valor)) {
            throw new InvalidArgumentException('El monto no es un número finito.');
        }

        return (int) round($valor * 1000);
    }

    /**
     * El monto en centavos enteros: 602.85 -> 60285.
     *
     * @deprecated Desde la migracion 019 los montos llevan hasta tres decimales y centavos() pierde el tercero:
     *             usar milesimas(). Se deja aqui, sin usos en src/, porque sus pruebas documentan la regla vieja.
     * Redondea al centavo mas
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
