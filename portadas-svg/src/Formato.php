<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Medidas físicas del libro, en mm: corte, sangrado, solapas y lomo. Una solapa de 0 significa que no hay. */
final class Formato
{
    public function __construct(
        public readonly float $ancho,
        public readonly float $alto,
        public readonly float $sangrado,
        public readonly float $solapas,
        public readonly float $lomo,
    ) {
    }
}
