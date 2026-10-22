<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;
use PortadasSvg\Svg;

/**
 * Adorno de una tradición cultural para la cubierta: un friso (una franja que se repite) y un emblema
 * (un dibujo centrado). Todo son trazos de línea de un solo color, hechos solo con líneas y círculos, así
 * que cada adorno cabe exactamente en la zona que se le da.
 */
abstract class Adorno
{
    /** Segmentos con los que se aproxima una circunferencia o una curva: a ese tamaño no se nota la diferencia. */
    protected const SEGMENTOS = 48;

    /** El adorno de una tradición (ver datos/tradiciones.json), o el genérico si no tiene o aún no hay uno. */
    public static function de(?string $tradicion): self
    {
        return match ($tradicion) {
            'griega' => new Griega(),
            'china' => new China(),
            'arabe' => new Arabe(),
            'medieval' => new Medieval(),
            default => new Generico(),
        };
    }

    /**
     * Franja que llena `zona` repitiendo un módulo. Devuelve '' si este adorno no tiene friso: la plantilla
     * pone entonces su adorno de siempre.
     */
    abstract public function friso(Rectangulo $zona, string $color): string;

    /** Dibujo centrado en (cx, cy) que cabe en un cuadrado de `2 × $radio` mm de lado. */
    abstract public function emblema(float $cx, float $cy, float $radio, string $color): string;

    /** Qué parte del radio ocupa de verdad el emblema, para colocar lo que va a su lado. */
    public function ocupa(): float
    {
        return 1.0;
    }

    /**
     * Repite `$modulo` a lo ancho de `$zona`: el número entero de módulos que más se acerca a `$ancho`,
     * estirados a lo ancho lo justo para llenarla sin sobras.
     *
     * @param callable(float, float): string $modulo recibe la x donde empieza y el ancho que tiene
     */
    protected static function repetir(Rectangulo $zona, float $ancho, callable $modulo): string
    {
        $cuantos = max(1, (int) round($zona->ancho / $ancho));
        $paso = $zona->ancho / $cuantos;
        $svg = '';
        for ($i = 0; $i < $cuantos; $i++) {
            $svg .= $modulo($zona->x + $i * $paso, $paso);
        }

        return $svg;
    }

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

    /** Grosor de línea para un adorno de `$alto` mm: ni tan fino que se pierda ni tan grueso que se empaste. */
    protected static function grosor(float $alto): float
    {
        return max(0.25, round($alto * 0.07, 2));
    }
}
