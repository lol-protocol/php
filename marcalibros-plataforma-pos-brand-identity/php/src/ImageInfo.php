<?php

namespace Marcalibros;

/** Datos de una imagen que ya pasó la inspección. Inmutable. */
final class ImageInfo
{
    public function __construct(
        public readonly string $path,
        public readonly string $mime,
        public readonly int $width,
        public readonly int $height,
    ) {
    }
}
