<?php

declare(strict_types=1);

namespace App;

final class Config
{
    public const MONEDA = '$';
    public const NOMBRE_SISTEMA = 'Panel de Cobros, Ingresos y Funnel';

    /**
     * Formatea un monto ya consolidado a USD (los agregados/KPIs siempre lo
     * estan). El signo va antes del simbolo ("-$58.00", no "$-58.00"): los
     * cobros netos pueden dar negativo cuando hay devoluciones.
     */
    public static function money(float $amount): string
    {
        return ($amount < 0 ? '-' : '') . self::MONEDA . number_format(abs($amount), 2);
    }
}
