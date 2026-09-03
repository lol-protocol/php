<?php

declare(strict_types=1);

namespace App;

final class Config
{
    public const MONEDA = '$';
    public const NOMBRE_SISTEMA = 'Panel de Cobros, Ingresos y Funnel';

    public static function money(float $amount): string
    {
        return self::MONEDA . number_format($amount, 2);
    }
}
