<?php

namespace Marcalibros;

/**
 * Cómo se recorta la imagen para que quede en proporción 7:1.
 *
 * Con zoom 1 se usa la mayor franja 7:1 que cabe en la imagen. Con más zoom
 * la franja se achica, y offsetX y offsetY dicen dónde ponerla: 0 es el borde
 * izquierdo o superior, 1 el derecho o inferior y 0.5 el centro.
 */
final class CropSettings
{
    public const MAX_ZOOM = 4.0;

    public function __construct(
        public readonly float $zoom = 1.0,
        public readonly float $offsetX = 0.5,
        public readonly float $offsetY = 0.5,
    ) {
        if (!is_finite($zoom) || $zoom < 1.0 || $zoom > self::MAX_ZOOM) {
            throw new BookmarkRejectedException(RejectionReason::InvalidCrop);
        }
        foreach ([$offsetX, $offsetY] as $offset) {
            if (!is_finite($offset) || $offset < 0.0 || $offset > 1.0) {
                throw new BookmarkRejectedException(RejectionReason::InvalidCrop);
            }
        }
    }

    /**
     * Crea el recorte a partir de datos de un formulario. Las claves son
     * "zoom", "x" e "y"; las que falten toman el valor por defecto. Acepta
     * números escritos como texto y rechaza cualquier otra cosa.
     *
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $read = static function (string $key, float $default) use ($input): float {
            if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
                return $default;
            }
            if (!is_int($input[$key]) && !is_float($input[$key]) && !is_string($input[$key])) {
                throw new BookmarkRejectedException(RejectionReason::InvalidCrop);
            }
            if (!is_numeric($input[$key])) {
                throw new BookmarkRejectedException(RejectionReason::InvalidCrop);
            }
            return (float) $input[$key];
        };

        return new self($read('zoom', 1.0), $read('x', 0.5), $read('y', 0.5));
    }
}
