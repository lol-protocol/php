<?php

namespace Marcalibros;

/**
 * Límites que se aplican a las imágenes subidas. Inmutable.
 *
 * El decodificador de imágenes necesita unos 4 bytes por píxel, y el proceso
 * llega a tener dos copias en memoria, así que maxPixels protege al servidor
 * de imágenes pequeñas en bytes pero enormes en píxeles (bombas de descompresión).
 */
final class Limits
{
    /**
     * @param int      $maxBytes     Tamaño máximo del archivo (5 MB por defecto).
     * @param int      $maxPixels    Ancho por alto máximo (16 millones por defecto, 4000x4000).
     * @param int      $minCropWidth Ancho mínimo, en píxeles de la imagen original, de la zona recortada.
     * @param int      $jpegQuality  Calidad de salida, de 1 a 100.
     * @param int|null $memoryBudget Bytes disponibles para procesar. Null usa memory_limit menos lo ya usado.
     */
    public function __construct(
        public readonly int $maxBytes = 5_242_880,
        public readonly int $maxPixels = 16_000_000,
        public readonly int $minCropWidth = 700,
        public readonly int $jpegQuality = 85,
        public readonly ?int $memoryBudget = null,
    ) {
        if ($maxBytes < 1 || $maxPixels < 1 || $minCropWidth < 1) {
            throw new \InvalidArgumentException('Los límites deben ser mayores que cero.');
        }
        if ($jpegQuality < 1 || $jpegQuality > 100) {
            throw new \InvalidArgumentException('La calidad JPEG debe estar entre 1 y 100.');
        }
    }
}
