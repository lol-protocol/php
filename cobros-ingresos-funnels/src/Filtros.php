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

    /**
     * El mismo largo de rango, inmediatamente anterior a [desde, hasta],
     * para poder comparar un periodo contra el anterior.
     * @return array{0: string, 1: string} [desde, hasta] en formato Y-m-d
     */
    public static function rangoAnterior(string $desde, string $hasta): array
    {
        $desdeD = new DateTimeImmutable($desde);
        $hastaD = new DateTimeImmutable($hasta);
        $dias = $hastaD->diff($desdeD)->days;

        $hastaAnterior = $desdeD->modify('-1 day');
        $desdeAnterior = $hastaAnterior->modify("-{$dias} days");

        return [$desdeAnterior->format('Y-m-d'), $hastaAnterior->format('Y-m-d')];
    }

    /**
     * El mismo rango de fechas pero un año calendario antes, para comparar
     * un periodo contra el mismo periodo del año pasado.
     * @return array{0: string, 1: string} [desde, hasta] en formato Y-m-d
     */
    public static function rangoAnioAnterior(string $desde, string $hasta): array
    {
        return [
            (new DateTimeImmutable($desde))->modify('-1 year')->format('Y-m-d'),
            (new DateTimeImmutable($hasta))->modify('-1 year')->format('Y-m-d'),
        ];
    }

    /**
     * Rango personalizado desde los inputs Desde/Hasta del filtro, si estan
     * completos y son fechas validas con desde <= hasta. Null si no aplica
     * (para que el llamador use el rango por meses en su lugar).
     * @return array{0: string, 1: string}|null
     */
    public static function rangoPersonalizado(): ?array
    {
        $desde = trim((string) ($_GET['desde'] ?? ''));
        $hasta = trim((string) ($_GET['hasta'] ?? ''));

        if ($desde === '' || $hasta === '' || !self::esFechaValida($desde) || !self::esFechaValida($hasta) || $desde > $hasta) {
            return null;
        }

        return [$desde, $hasta];
    }

    /** El rango personalizado si esta presente y es valido; si no, el rango por meses. */
    public static function rangoActivo(): array
    {
        return self::rangoPersonalizado() ?? self::rango(self::meses());
    }

    /** true si $fecha es una fecha real en formato Y-m-d (rechaza "2026-02-30", texto suelto, etc.). */
    public static function esFechaValida(string $fecha): bool
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        return $d !== false && $d->format('Y-m-d') === $fecha;
    }
}
