<?php

declare(strict_types=1);

/**
 * Sanea el monto (pagos/reembolsos) de un registro y lo escribe en $limpio.
 * Si la moneda cruda es inválida, se asume la del país del usuario en vez de
 * descartar el pago entero: el monto sí se registró.
 */
function saneador_aplicar_monto(array &$limpio, array $crudo, string $tipo, array $usuario, array $contexto): void
{
    if (!in_array($tipo, SANEADOR_TIPOS_CON_MONTO, true)) {
        return;
    }

    $monto = saneador_numero($crudo['amount'] ?? null);
    if ($monto === null || $monto <= 0) {
        return;
    }

    $moneda = saneador_moneda($crudo['currency'] ?? null, $contexto['monedas_validas'])
        ?? $contexto['moneda_por_pais'][$usuario['country']]
        ?? 'USD';
    $tasa = $contexto['tasa_por_moneda'][$moneda] ?? 1.0;

    $limpio['amount_local'] = round($monto, 2);
    $limpio['currency'] = $moneda;
    $limpio['amount_usd'] = round($monto / $tasa, 2);
}
