<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

final class Filtros
{
    public static function meses(): int
    {
        $meses = (int) ($_GET['meses'] ?? 6);
        return in_array($meses, [3, 6, 12], true) ? $meses : 6;
    }

    /** @return array{0: string, 1: string} [desde, hasta] en formato Y-m-d */
    public static function rango(int $meses): array
    {
        $hasta = new DateTimeImmutable('today');
        $desde = $hasta->modify("-{$meses} months");
        return [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
    }
}
