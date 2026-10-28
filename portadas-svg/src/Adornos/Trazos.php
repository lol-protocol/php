<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Svg;

/** Trazos de línea con los que se dibujan los adornos y los motivos: líneas quebradas, círculos y curvas. */
abstract class Trazos
{
    /** Segmentos con los que se aproxima una circunferencia o una curva: a ese tamaño no se nota la diferencia. */
    protected const SEGMENTOS = 48;

    /**
     * Línea quebrada por los puntos dados.
     *
     * @param list<array{float, float}> $puntos
     */
    protected static function linea(array $puntos, string $color, float $grosor, bool $cerrada = false): string
    {
        $lista = implode(' ', array_map(static fn (array $punto): string => Svg::n($punto[0]) . ',' . Svg::n($punto[1]), $puntos));

        return Svg::elemento($cerrada ? 'polygon' : 'polyline', [
            'points' => $lista,
            'fill' => 'none',
            'stroke' => $color,
            'stroke-width' => $grosor,
            'stroke-linejoin' => 'miter',
            'stroke-linecap' => 'square',
        ]);
    }

    protected static function circulo(float $cx, float $cy, float $radio, string $color, float $grosor, bool $relleno = false): string
    {
        return Svg::elemento('circle', ['cx' => $cx, 'cy' => $cy, 'r' => $radio, 'fill' => $relleno ? $color : 'none'] + ($relleno ? [] : ['stroke' => $color, 'stroke-width' => $grosor]));
    }

    /**
     * Puntos de un arco de circunferencia, desde `$desde` hasta `$hasta` grados (0° a la derecha, y los
     * grados crecen hacia abajo, como el eje y del SVG).
     *
     * @return list<array{float, float}>
     */
    protected static function arco(float $cx, float $cy, float $radio, float $desde, float $hasta): array
    {
        $puntos = [];
        for ($i = 0; $i <= self::SEGMENTOS; $i++) {
            $angulo = deg2rad($desde + ($hasta - $desde) * $i / self::SEGMENTOS);
            $puntos[] = [$cx + $radio * cos($angulo), $cy + $radio * sin($angulo)];
        }

        return $puntos;
    }

    /**
     * Puntos de una elipse de semiejes `$rx` y `$ry`, girada `$giro` grados.
     *
     * @return list<array{float, float}>
     */
    protected static function elipse(float $cx, float $cy, float $rx, float $ry, float $giro = 0.0): array
    {
        $puntos = [];
        $seno = sin(deg2rad($giro));
        $coseno = cos(deg2rad($giro));
        for ($i = 0; $i < self::SEGMENTOS; $i++) {
            $angulo = 2 * M_PI * $i / self::SEGMENTOS;
            $x = $rx * cos($angulo);
            $y = $ry * sin($angulo);
            $puntos[] = [$cx + $x * $coseno - $y * $seno, $cy + $x * $seno + $y * $coseno];
        }

        return $puntos;
    }

    /**
     * Espiral que parte de su borde exterior (a `$radio` del centro, en el ángulo `$inicio`) y gira
     * `$vueltas` vueltas hacia dentro, con el signo de `$sentido` (1 horario en pantalla, -1 antihorario).
     *
     * @return list<array{float, float}>
     */
    protected static function espiral(float $cx, float $cy, float $radio, float $inicio, float $vueltas, int $sentido = 1): array
    {
        $puntos = [];
        $pasos = (int) ceil(self::SEGMENTOS * $vueltas);
        for ($i = 0; $i <= $pasos; $i++) {
            $t = $i / $pasos;
            $angulo = deg2rad($inicio) + $sentido * 2 * M_PI * $vueltas * $t;
            $r = $radio * (1 - 0.85 * $t);
            $puntos[] = [$cx + $r * cos($angulo), $cy + $r * sin($angulo)];
        }

        return $puntos;
    }

    /**
     * Hoja o pétalo: una forma alargada con las dos puntas afiladas, de `$largo` por `$ancho`, con su
     * centro en (cx, cy) y girada `$giro` grados (0° apunta a la derecha).
     *
     * @return list<array{float, float}>
     */
    protected static function hoja(float $cx, float $cy, float $largo, float $ancho, float $giro = 0.0): array
    {
        $puntos = [];
        $seno = sin(deg2rad($giro));
        $coseno = cos(deg2rad($giro));
        $pasos = self::SEGMENTOS / 2;
        for ($i = 0; $i < 2 * $pasos; $i++) {
            // Primero la curva de ida por un lado y luego la de vuelta por el otro.
            $t = $i < $pasos ? $i / $pasos : ($i - $pasos) / $pasos;
            $x = ($t - 0.5) * $largo;
            $y = ($i < $pasos ? 1 : -1) * $ancho / 2 * sin(M_PI * $t);
            $puntos[] = [$cx + $x * $coseno - $y * $seno, $cy + $x * $seno + $y * $coseno];
        }

        return $puntos;
    }

    /**
     * Vértices de un polígono regular con `$lados` lados.
     *
     * @return list<array{float, float}>
     */
    protected static function poligono(float $cx, float $cy, float $radio, int $lados, float $giro = 0.0): array
    {
        $puntos = [];
        for ($i = 0; $i < $lados; $i++) {
            $angulo = deg2rad($giro + 360 * $i / $lados);
            $puntos[] = [$cx + $radio * cos($angulo), $cy + $radio * sin($angulo)];
        }

        return $puntos;
    }

    /** Grosor de línea para un adorno de `$alto` mm: fino, pero no tanto que se pierda en pantalla. */
    protected static function grosor(float $alto): float
    {
        return max(0.18, round($alto * 0.045, 2));
    }
}
