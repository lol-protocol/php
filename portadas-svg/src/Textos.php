<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Los textos que lleva la cubierta. Un texto vacío significa que no se pone. */
final class Textos
{
    public function __construct(
        public readonly string $titulo,
        public readonly string $subtitulo,
        public readonly string $autor,
        public readonly string $traductor,
        public readonly string $editorial,
        public readonly string $coleccion,
        public readonly string $isbn,
        public readonly string $contratapa,
        public readonly string $solapaContratapa,
        public readonly string $solapaPortada,
    ) {
    }
}
