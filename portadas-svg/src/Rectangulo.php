<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Rectángulo en milímetros, con el origen arriba a la izquierda, como en SVG. */
final class Rectangulo
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $ancho,
        public readonly float $alto,
    ) {
    }
}
