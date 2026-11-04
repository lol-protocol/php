<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Lo único que estos analizadores devuelven de una persona. Es a propósito poco:
 * no hay correo, teléfono, fecha de nacimiento, contactos ni publicaciones.
 *
 * Todo perfil sale con `posiblemente_vivo` y `revision_humana` en verdadero: un
 * perfil de red social casi siempre es de una persona viva, y nada de esto se
 * publica sin que una persona lo revise (ver PRIVACIDAD-Y-HONRA.md en la raíz).
 */
final class Perfil
{
    /**
     * @param list<string> $avisos
     */
    public function __construct(
        public readonly string $red,
        public readonly ?string $url,
        public readonly ?string $usuario,
        public readonly ?string $nombre,
        public readonly ?string $titular,
        public readonly ?string $lugar,
        public readonly ?string $descripcion,
        public readonly ?string $capturado,
        public readonly array $avisos = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'red' => $this->red,
            'url' => $this->url,
            'usuario' => $this->usuario,
            'nombre' => $this->nombre,
            'titular' => $this->titular,
            'lugar' => $this->lugar,
            'descripcion' => $this->descripcion,
            'capturado' => $this->capturado,
            'posiblemente_vivo' => true,
            'revision_humana' => true,
            'avisos' => $this->avisos,
        ];
    }
}
