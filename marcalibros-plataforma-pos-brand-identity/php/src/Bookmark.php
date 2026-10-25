<?php

namespace Marcalibros;

/**
 * Un marcalibro propio ya guardado. Los nombres de archivo no llevan carpeta:
 * guarda en disco solo estos nombres y arma la ruta al servirlos.
 */
final class Bookmark
{
    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_LIBRARY = 'library';

    /**
     * @param array<string, string> $files Nombre de archivo por tamaño ("1x", "2x").
     */
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly ?string $label,
        public readonly array $files,
        public readonly int $width,
        public readonly int $height,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'label' => $this->label,
            'files' => $this->files,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
