<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Medida sin fuente: cada letra ocupa lo mismo. Sirve para probar el ajuste de texto con números redondos. */
final class FactorFijo implements Medida
{
    public function __construct(private readonly float $factor)
    {
    }

    public function em(string $texto): float
    {
        return mb_strlen($texto) * $this->factor;
    }
}
