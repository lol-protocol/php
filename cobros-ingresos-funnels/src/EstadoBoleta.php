<?php

declare(strict_types=1);

namespace App;

final class EstadoBoleta
{
    /**
     * Calcula el saldo y el estado de una boleta a partir de lo pagado y su
     * vencimiento. No se guarda en la base para que nunca quede desincronizado.
     *
     * @return array{saldo: float, estado: string}
     */
    public static function calcular(float $monto, float $pagado, string $fechaVencimiento, ?string $hoy = null): array
    {
        $hoy ??= date('Y-m-d');
        $saldo = round($monto - $pagado, 2);

        $estado = match (true) {
            $saldo <= 0.01 => 'pagada',
            $fechaVencimiento < $hoy => 'vencida',
            $pagado > 0 => 'parcial',
            default => 'pendiente',
        };

        return ['saldo' => $saldo, 'estado' => $estado];
    }
}
