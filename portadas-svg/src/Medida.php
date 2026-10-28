<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Mide el ancho que ocupa un texto escrito con una fuente concreta. */
interface Medida
{
    /**
     * Ancho de `texto` en múltiplos del tamaño de letra (em), sin contar el espaciado entre letras.
     * Para saber cuántos milímetros ocupa, se multiplica por el tamaño de letra en mm.
     */
    public function em(string $texto): float;
}
