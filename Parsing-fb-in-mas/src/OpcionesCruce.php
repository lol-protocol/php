<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use InvalidArgumentException;

/**
 * Los umbrales del cruce. Los valores por defecto son una primera aproximación, no medida con datos reales:
 * conviene ajustarlos mirando los candidatos que salen (el puntaje y la evidencia van en cada uno).
 */
final class OpcionesCruce
{
    /**
     * @param float $puntajeMinimo desde qué puntaje (0 a 1) una pareja es candidata
     * @param int $maxAnios diferencia máxima, en años, entre los nacimientos (si los dos lo tienen)
     * @param int $palabrasMinimas cuántas palabras del nombre deben coincidir
     * @throws InvalidArgumentException si un valor no tiene sentido
     */
    public function __construct(
        public readonly float $puntajeMinimo = 0.6,
        public readonly int $maxAnios = 5,
        public readonly int $palabrasMinimas = 2,
    ) {
        if ($puntajeMinimo <= 0.0 || $puntajeMinimo > 1.0) {
            throw new InvalidArgumentException('El puntaje mínimo va entre 0 (sin incluir) y 1');
        }
        if ($maxAnios < 0) {
            throw new InvalidArgumentException('La diferencia máxima de años no puede ser negativa');
        }
        if ($palabrasMinimas < 1) {
            throw new InvalidArgumentException('Hace falta al menos una palabra del nombre en común');
        }
    }
}
