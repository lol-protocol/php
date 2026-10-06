<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

final class Filtros
{
    /**
     * Los periodos que ofrece el selector, con el texto que se muestra: la unica
     * lista, la que lee meses() para validar y la que dibuja views/_filtro_fechas.php
     * (el <select> estaba copiado en cinco vistas, y los valores validos aparte).
     */
    public const OPCIONES_MESES = [3 => 'Últimos 3 meses', 6 => 'Últimos 6 meses', 12 => 'Últimos 12 meses'];
    public const MESES_POR_DEFECTO = 6;

    public static function meses(): int
    {
        $pedido = self::mesesPedido();

        return $pedido !== null && self::mesesEsValido($pedido) ? (int) $pedido : self::MESES_POR_DEFECTO;
    }

    /** Lo que vino en ?meses=, o null si no vino (vacio cuenta como que no vino). */
    private static function mesesPedido(): ?string
    {
        $pedido = trim((string) ($_GET['meses'] ?? ''));

        return $pedido === '' ? null : $pedido;
    }

    private static function mesesEsValido(string $pedido): bool
    {
        return ctype_digit($pedido) && array_key_exists((int) $pedido, self::OPCIONES_MESES);
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
        [$desde, $hasta] = self::rangoIngresado();

        if ($desde === '' || $hasta === '' || self::motivoDelRangoIgnorado() !== null) {
            return null;
        }

        return [$desde, $hasta];
    }

    /**
     * Lo que se tipeo en Desde y Hasta, tal cual (sin los espacios de los
     * bordes), valga o no: el formulario lo vuelve a mostrar para que se
     * corrija en vez de borrarlo.
     * @return array{0: string, 1: string}
     */
    public static function rangoIngresado(): array
    {
        return [trim((string) ($_GET['desde'] ?? '')), trim((string) ($_GET['hasta'] ?? ''))];
    }

    /**
     * Por que se ignora lo tipeado en Desde/Hasta, o null si no hay nada que
     * ignorar: no se tipeo nada, o es un rango completo, valido y en orden.
     */
    private static function motivoDelRangoIgnorado(): ?string
    {
        [$desde, $hasta] = self::rangoIngresado();

        if ($desde === '' && $hasta === '') {
            return null;
        }
        if ($desde === '' || $hasta === '') {
            return 'Para usar un rango exacto completá «Desde» y «Hasta»';
        }
        if (!self::esFechaValida($desde) || !self::esFechaValida($hasta)) {
            return '«Desde» y «Hasta» tienen que ser fechas válidas';
        }
        if ($desde > $hasta) {
            return '«Desde» no puede ser posterior a «Hasta»';
        }

        return null;
    }

    /**
     * Lo pedido por URL que no se pudo respetar (un periodo que no existe, un
     * rango incompleto, invalido o al reves), para decirlo en pantalla: antes
     * cada caso se resolvia distinto y todos en silencio, y un rango al reves
     * ademas borraba lo tipeado.
     * @return list<array{tipo: string, texto: string}>
     */
    public static function avisos(): array
    {
        $avisos = [];

        $pedido = self::mesesPedido();
        if ($pedido !== null && !self::mesesEsValido($pedido)) {
            $avisos[] = Avisos::atencion('El período pedido no es válido; se muestran los últimos ' . self::MESES_POR_DEFECTO . ' meses.');
        }

        $motivo = self::motivoDelRangoIgnorado();
        if ($motivo !== null) {
            $avisos[] = Avisos::atencion($motivo . '; mientras tanto se muestra el período elegido.');
        }

        return $avisos;
    }

    /**
     * meses + el rango activo (personalizado si esta presente y es valido,
     * si no el de meses) + si ese rango activo es el personalizado: el combo
     * que Dashboard/Cobros/Pagos/Funnel/Cohortes repetian cada uno por su
     * cuenta en 3 lineas. Ademas, lo que necesita views/_filtro_fechas.php
     * (lo tipeado en Desde/Hasta) y los avisos de lo que se ignoro.
     * @return array{meses: int, desde: string, hasta: string, personalizado: bool, desdeIngresado: string, hastaIngresado: string, avisos: list<array{tipo: string, texto: string}>}
     */
    public static function rangoActivo(): array
    {
        $meses = self::meses();
        $personalizado = self::rangoPersonalizado();
        [$desde, $hasta] = $personalizado ?? self::rango($meses);
        [$desdeIngresado, $hastaIngresado] = self::rangoIngresado();

        return [
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'personalizado' => $personalizado !== null,
            'desdeIngresado' => $desdeIngresado,
            'hastaIngresado' => $hastaIngresado,
            'avisos' => self::avisos(),
        ];
    }

    /** true si $fecha es una fecha real en formato Y-m-d (rechaza "2026-02-30", texto suelto, etc.). */
    public static function esFechaValida(string $fecha): bool
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        return $d !== false && $d->format('Y-m-d') === $fecha;
    }
}
