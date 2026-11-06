<?php

declare(strict_types=1);

namespace App;

use App\Repositories\MonedaRepository;

/**
 * Las reglas de negocio de un pago, separadas de PagosController: que la boleta sea del cliente y
 * este vigente, que la fecha y el monto cuadren con ella, que los campos sueltos esten bien. Son
 * funciones puras (reciben la boleta ya leida y devuelven si cumple o el mensaje del primer error),
 * asi que se prueban sin HTTP ni base y las comparten el alta y la edicion. El controlador solo
 * decide que hacer con el resultado.
 */
final class ReglasDePago
{
    /**
     * Una boleta solo es un origen valido para el pago de $clienteId si
     * existe, es de ese mismo cliente y no esta anulada (evita registrar un
     * cobro contra una boleta ajena o ya sin efecto).
     */
    public static function boletaEsValidaParaCliente(?array $boleta, int $clienteId): bool
    {
        return $boleta !== null && (int) $boleta['cliente_id'] === $clienteId && !$boleta['anulada'];
    }

    /** No se puede registrar un pago con fecha anterior a que la boleta fue emitida. */
    public static function fechaPagoEsValida(string $fechaPago, array $boleta): bool
    {
        return $fechaPago >= $boleta['fecha_emision'];
    }

    /**
     * Un pago cuya boleta ya fue anulada queda congelado: al anularla se
     * emitio una nota de credito por lo que estaba cobrado en ese momento, y
     * esa nota no se recalcula. Si despues se pudiera anular o editar el
     * pago, la plata se contaria dos veces (el pago sale de la caja y la
     * nota lo sigue devolviendo) o la devolucion quedaria corta. Es la regla
     * simetrica de boletaEsValidaParaCliente(), que ya impide cargar un pago
     * nuevo contra una boleta anulada.
     */
    public static function boletaAnuladaCongelaElPago(?array $boleta): bool
    {
        return $boleta !== null && (bool) $boleta['anulada'];
    }

    /**
     * Un pago no puede pasarse del saldo, ni por un centavo. Se compara en
     * centavos enteros (ver Dinero): el margen de un centavo que tenia antes
     * evitaba que el ruido de la coma flotante rechazara pagar justo el saldo,
     * pero dejaba cobrar un centavo de mas.
     */
    public static function montoNoSuperaElSaldo(float $monto, array $boleta): bool
    {
        return Dinero::centavos($monto) <= Dinero::centavos($boleta['saldo']);
    }

    /**
     * Misma regla que montoNoSuperaElSaldo() pero al EDITAR un pago ya
     * existente: BoletaRepository::porId() calcula el saldo restando todos
     * los pagos vigentes de la boleta, este mismo pago incluido, asi que
     * hay que sumarle de vuelta $montoViejo antes de comparar contra el
     * monto nuevo (si no, el pago se estaria descontando a si mismo).
     *
     * Dejar el monto como estaba (o bajarlo) nunca se rechaza, ni en una boleta
     * que ya estaba sobrecobrada, con saldo negativo: sin eso un pago de esa
     * boleta no se podria editar ni para corregirle la fecha.
     */
    public static function montoNoSuperaElSaldoAlEditar(float $montoNuevo, array $boleta, float $montoViejo): bool
    {
        $viejo = Dinero::centavos($montoViejo);

        return Dinero::centavos($montoNuevo) <= max($viejo, Dinero::centavos($boleta['saldo']) + $viejo);
    }

    /** El metodo tiene que ser uno de los que ofrece el formulario: lo valida la app y lo restringe la base (migracion 004). */
    public static function metodoEsValido(string $metodo): bool
    {
        return array_key_exists($metodo, Etiquetas::metodosPago());
    }

    /**
     * El primer error en los campos sueltos de un pago, o null si estan bien: los
     * chequeos que comparten el alta y la edicion, con los mismos mensajes (antes
     * escritos dos veces). Devuelve el mensaje y no un booleano porque el
     * formulario lo muestra tal cual, igual que Validacion::primerTextoLargo().
     *
     * $moneda es la del pago: en una moneda sin decimales (yen, won) el monto tiene que ser entero.
     * Sin ella solo se chequea el rango.
     */
    public static function errorDeLosCampos(string $fechaPago, string $metodo, float $monto, ?string $moneda = null, ?string $hoy = null): ?string
    {
        if (Validacion::faltanCampos([$fechaPago, $metodo], $monto)) {
            return 'Completá todos los campos con un monto válido.';
        }
        if ($moneda !== null && !Validacion::montoCabeEnLosDecimales($monto, MonedaRepository::decimales($moneda))) {
            return sprintf('En %s los montos van sin decimales.', $moneda);
        }
        if (!self::metodoEsValido($metodo)) {
            return 'Elegí un método de pago válido.';
        }
        if (!Validacion::fechaEsValida($fechaPago)) {
            return 'La fecha de pago no es válida.';
        }
        // Un pago es algo que ya ocurrió: con fecha futura entraría en los ingresos de un mes que no termina.
        if (!Validacion::fechaNoEsFutura($fechaPago, $hoy)) {
            return 'La fecha de pago no puede ser futura.';
        }

        return null;
    }

    /**
     * El primer error de un pago contra su boleta, o null: la fecha no puede ser
     * anterior a la emision y el monto no puede pasarse del saldo. Al editar se
     * pasa $montoViejo, lo que el pago ya tenia, porque el saldo de la boleta ya
     * lo descuenta (ver montoNoSuperaElSaldoAlEditar()); al dar de alta, null.
     *
     * @param array<string, mixed> $boleta
     */
    public static function errorContraLaBoleta(string $fechaPago, float $monto, array $boleta, ?float $montoViejo = null): ?string
    {
        if (!self::fechaPagoEsValida($fechaPago, $boleta)) {
            return 'La fecha de pago no puede ser anterior a la emisión de la boleta.';
        }

        $cabe = $montoViejo === null
            ? self::montoNoSuperaElSaldo($monto, $boleta)
            : self::montoNoSuperaElSaldoAlEditar($monto, $boleta, $montoViejo);
        if (!$cabe) {
            return 'El monto supera el saldo pendiente de la boleta.';
        }

        return null;
    }
}
