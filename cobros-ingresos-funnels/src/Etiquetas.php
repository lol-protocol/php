<?php

declare(strict_types=1);

namespace App;

/**
 * Traduce los valores de conjunto cerrado (estado de boleta, metodo de pago,
 * canal de adquisicion) a su etiqueta en español. Antes cada vista que los
 * mostraba tenia su propia copia del mismo array; un valor nuevo (como paso
 * con nota_credito en Auditoria) se agregaba en una copia y se olvidaba en
 * las demas.
 */
final class Etiquetas
{
    private const ESTADOS_BOLETA = [
        'pagada' => 'Pagada',
        'pendiente' => 'Pendiente',
        'parcial' => 'Parcial',
        'vencida' => 'Vencida',
        'anulada' => 'Anulada',
    ];

    private const METODOS_PAGO = [
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
        'efectivo' => 'Efectivo',
    ];

    private const CANALES = [
        'organico' => 'Orgánico',
        'ads' => 'Ads',
        'referido' => 'Referido',
        'redes_sociales' => 'Redes sociales',
        'email' => 'Email',
    ];

    public static function estadoBoleta(string $estado): string
    {
        return self::ESTADOS_BOLETA[$estado] ?? $estado;
    }

    /** @return array<string, string> para armar el <select> de filtro/alta. */
    public static function estadosBoleta(): array
    {
        return self::ESTADOS_BOLETA;
    }

    public static function metodoPago(string $metodo): string
    {
        return self::METODOS_PAGO[$metodo] ?? $metodo;
    }

    /** @return array<string, string> para armar el <select> de metodo. */
    public static function metodosPago(): array
    {
        return self::METODOS_PAGO;
    }

    public static function canal(string $canal): string
    {
        return self::CANALES[$canal] ?? $canal;
    }
}
