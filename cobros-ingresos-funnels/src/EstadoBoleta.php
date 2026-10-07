<?php

declare(strict_types=1);

namespace App;

final class EstadoBoleta
{
    /**
     * Calcula el saldo y el estado de una boleta a partir de lo pagado y su
     * vencimiento. No se guarda en la base para que nunca quede desincronizado.
     * Una boleta anulada siempre queda en estado 'anulada', sin importar el saldo.
     *
     * La misma regla esta escrita en SQL en BoletaRepository::ESTADO_SQL, que es
     * lo que usa el filtro de estado de Cobros para paginar en la base:
     * EstadoBoletaSqlTest compara las dos, asi que un cambio aca va con el otro.
     *
     * @return array{saldo: float, estado: string}
     */
    public static function calcular(float $monto, float $pagado, string $fechaVencimiento, ?string $hoy = null, bool $anulada = false): array
    {
        $hoy ??= date('Y-m-d');
        $saldo = round($monto - $pagado, 2);

        $estado = match (true) {
            $anulada => 'anulada',
            $saldo <= 0.01 => 'pagada',
            $fechaVencimiento < $hoy => 'vencida',
            $pagado > 0 => 'parcial',
            default => 'pendiente',
        };

        return ['saldo' => $saldo, 'estado' => $estado];
    }
}
