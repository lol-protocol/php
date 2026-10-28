<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/**
 * Adorno de una tradición cultural para la cubierta: un friso (una franja que se repite) y un emblema
 * (un dibujo centrado). Todo son trazos de línea de un solo color, hechos solo con líneas y círculos, así
 * que cada adorno cabe exactamente en la zona que se le da.
 */
abstract class Adorno extends Trazos
{
    /** El adorno de una tradición (ver datos/tradiciones.json), o el genérico si no tiene o aún no hay uno. */
    public static function de(?string $tradicion): self
    {
        return match ($tradicion) {
            'griega' => new Griega(),
            'romana' => new Romana(),
            'egipcia' => new Egipcia(),
            'mesopotamica' => new Mesopotamica(),
            'hebrea' => new Hebrea(),
            'persa' => new Persa(),
            'arabe' => new Arabe(),
            'india' => new India(),
            'china' => new China(),
            'japonesa' => new Japonesa(),
            'celta' => new Celta(),
            'nordica' => new Nordica(),
            'medieval' => new Medieval(),
            'renacentista' => new Renacentista(),
            'precolombina' => new Precolombina(),
            'moderna' => new Moderna(),
            default => new Generico(),
        };
    }

    /**
     * Franja que llena `zona` repitiendo un módulo. Devuelve '' si este adorno no tiene friso: la plantilla
     * pone entonces su adorno de siempre.
     */
    abstract public function friso(Rectangulo $zona, string $color): string;

    /**
     * Dibujo centrado en (cx, cy) que cabe en un cuadrado de `2 × $radio` mm de lado. El grosor de la línea
     * sale del radio; con `$trazo` se fija en mm, para un emblema grande de línea fina.
     */
    abstract public function emblema(float $cx, float $cy, float $radio, string $color, ?float $trazo = null): string;

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
}
