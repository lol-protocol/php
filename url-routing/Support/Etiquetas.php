<?php

declare(strict_types=1);

namespace App\Support;

/** Human-readable labels for the codes stored in the database. */
final class Etiquetas
{
    private const RELACIONES = [
        'padre' => 'Padre',
        'madre' => 'Madre',
        'conyuge' => 'Cónyuge',
        'hermano' => 'Hermano/a',
        'hijo' => 'Hijo/a',
        'padrino' => 'Padrino/madrina',
        'tutor' => 'Tutor/a',
        'otro' => 'Otro vínculo',
    ];

    private const SUCESOS = [
        'nacimiento' => 'Nacimiento',
        'bautizo' => 'Bautizo',
        'matrimonio' => 'Matrimonio',
        'defuncion' => 'Defunción',
        'migracion' => 'Migración',
        'censo' => 'Censo',
    ];

    private const ESTADOS_ORDEN = [
        'pendiente' => 'Pendiente de pago',
        'pagada' => 'Pagada',
        'enviada' => 'Enviada',
        'entregada' => 'Entregada',
        'cancelada' => 'Cancelada',
        'devuelta' => 'Devuelta',
    ];

    private const ATRIBUTOS = ['color' => 'Color', 'talla' => 'Talla', 'material' => 'Material'];

    public static function relacion(string $codigo): string
    {
        return self::RELACIONES[$codigo] ?? self::capitalizar($codigo);
    }

    public static function suceso(string $codigo): string
    {
        return self::SUCESOS[$codigo] ?? self::capitalizar($codigo);
    }

    public static function estadoOrden(string $codigo): string
    {
        return self::ESTADOS_ORDEN[$codigo] ?? self::capitalizar($codigo);
    }

    public static function atributo(string $codigo): string
    {
        return self::ATRIBUTOS[$codigo] ?? self::capitalizar($codigo);
    }

    /** "Padres", "Abuelos", "Bisabuelos", then "Generación N". */
    public static function generacion(int $n, bool $ascendente): string
    {
        $nombres = $ascendente
            ? [1 => 'Padres', 2 => 'Abuelos', 3 => 'Bisabuelos', 4 => 'Tatarabuelos']
            : [1 => 'Hijos', 2 => 'Nietos', 3 => 'Bisnietos', 4 => 'Tataranietos'];

        return $nombres[$n] ?? "Generación {$n}";
    }

    private static function capitalizar(string $texto): string
    {
        return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }
}
