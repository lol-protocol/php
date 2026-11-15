<?php

declare(strict_types=1);

namespace App;

use App\Repositories\MonedaRepository;

/**
 * Las reglas de negocio de una boleta, separadas de BoletasController: los campos del formulario,
 * que el vencimiento no sea anterior a la emision y, al editar, que el monto cubra lo ya cobrado y la
 * emision no pase del primer pago. Son funciones puras, asi que se prueban sin HTTP ni base y las
 * comparten el alta y la edicion. El controlador solo decide que hacer con el resultado.
 */
final class ReglasDeBoleta
{
    /**
     * Al editar una boleta, el monto no puede bajar de lo que ya se cobro
     * (dejaria un saldo negativo y la boleta se seguiria mostrando como
     * "pagada" sin avisar que en realidad se sobrecobro). Ni por un centavo:
     * se compara en centavos enteros (ver Dinero), sin el margen de un centavo
     * que antes hacia pasar un monto de 0.01 menos que lo cobrado.
     */
    public static function montoCubreLoYaCobrado(float $monto, array $boleta): bool
    {
        return Dinero::milesimas($monto) >= Dinero::milesimas($boleta['pagado']);
    }

    /** Una boleta no puede vencer antes de haber sido emitida. */
    public static function vencimientoNoAnteriorALaEmision(string $fechaEmision, string $fechaVencimiento): bool
    {
        return $fechaVencimiento >= $fechaEmision;
    }

    /**
     * Al editar, la emision no puede quedar despues del primer pago que la
     * boleta ya tiene: seria una boleta cobrada antes de existir (el espejo
     * de la validacion que ya hace PagosController del lado del pago).
     */
    public static function emisionNoPosteriorAlPrimerPago(string $fechaEmision, array $boleta): bool
    {
        return $boleta['primer_pago'] === null || $fechaEmision <= $boleta['primer_pago'];
    }

    /**
     * El primer error en los campos de una boleta, o null si estan bien. Son los
     * chequeos que comparten el alta y la edicion, con los mismos mensajes: antes
     * estaban escritos dos veces y un mensaje corregido en una podia quedar viejo
     * en la otra. Devuelve el mensaje y no un booleano porque el formulario lo
     * muestra tal cual, igual que Validacion::primerTextoLargo(); lo que es solo
     * de la edicion (lo ya cobrado, el primer pago) lo chequea editar().
     *
     * $moneda es la de la boleta: en una moneda sin decimales (yen, won) el monto tiene que ser entero.
     * Sin ella solo se chequea el rango.
     */
    public static function errorDeLosCampos(string $concepto, float $monto, string $fechaEmision, string $fechaVencimiento, ?string $moneda = null): ?string
    {
        if (Validacion::faltanCampos([$concepto, $fechaEmision, $fechaVencimiento], $monto, $moneda !== null ? MonedaRepository::decimales($moneda) : 2)) {
            return 'Completá todos los campos con un monto válido.';
        }
        if ($moneda !== null && !Validacion::montoCabeEnLosDecimales($monto, MonedaRepository::decimales($moneda))) {
            return sprintf('En %s los montos van sin decimales.', $moneda);
        }
        if (($largo = Validacion::primerTextoLargo([['El concepto', $concepto, Validacion::MAX_CONCEPTO]])) !== null) {
            return $largo;
        }
        if (!Validacion::fechaEsValida($fechaEmision) || !Validacion::fechaEsValida($fechaVencimiento)) {
            return 'La fecha de emisión o de vencimiento no es válida.';
        }
        if (!self::vencimientoNoAnteriorALaEmision($fechaEmision, $fechaVencimiento)) {
            return 'El vencimiento no puede ser anterior a la emisión.';
        }

        return null;
    }
}
