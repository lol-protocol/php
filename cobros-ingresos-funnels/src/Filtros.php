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
        return [self::restarMeses($hasta, $meses)->format('Y-m-d'), $hasta->format('Y-m-d')];
    }

    /**
     * Resta meses sin que el dia se desborde al mes siguiente. PHP resuelve
     * "2026-05-31 -3 months" como 2026-03-03, porque febrero no tiene 31 dias
     * y el modify() se pasa de largo: los "ultimos 3 meses" arrancaban tres
     * dias tarde, en silencio, cada vez que hoy caia cerca de fin de mes. La
     * fecha correcta es el ultimo dia real del mes destino (2026-02-28).
     */
    public static function restarMeses(DateTimeImmutable $fecha, int $meses): DateTimeImmutable
    {
        $resultado = $fecha->modify("-{$meses} months");
        if ($resultado->format('j') === $fecha->format('j')) {
            return $resultado;
        }

        return $resultado->modify('first day of this month')->modify('-1 day');
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
            self::restarMeses(new DateTimeImmutable($desde), 12)->format('Y-m-d'),
            self::restarMeses(new DateTimeImmutable($hasta), 12)->format('Y-m-d'),
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

    /**
     * meses + el rango activo (personalizado si esta presente y es valido,
     * si no el de meses) + si ese rango activo es el personalizado: el combo
     * que Dashboard/Cobros/Pagos/Funnel/Cohortes repetian cada uno por su
     * cuenta en 3 lineas.
     * @return array{meses: int, desde: string, hasta: string, personalizado: bool}
     */
    public static function rangoActivo(): array
    {
        $meses = self::meses();
        $personalizado = self::rangoPersonalizado();
        [$desde, $hasta] = $personalizado ?? self::rango($meses);

        return [
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'personalizado' => $personalizado !== null,
        ];
    }

    /** true si $fecha es una fecha real en formato Y-m-d (rechaza "2026-02-30", texto suelto, etc.). */
    public static function esFechaValida(string $fecha): bool
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        return $d !== false && $d->format('Y-m-d') === $fecha;
    }
}
