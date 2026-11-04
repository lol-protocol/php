<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Lo único que estos analizadores devuelven de una persona. Es a propósito poco:
 * no hay correo, teléfono, fecha de nacimiento, contactos ni publicaciones.
 *
 * `revision_humana` sale siempre en verdadero. `posiblemente_vivo` también, salvo que la
 * propia fuente diga que la persona murió (hay fecha de muerte): solo entonces se
 * guardan fechas, lugares y parientes. De una persona viva no se guarda ninguno.
 * Nada de esto se publica sin que una persona lo revise (ver PRIVACIDAD-Y-HONRA.md).
 */
final class Perfil
{
    /**
     * @param list<string> $avisos
     * @param list<array{relacion: string, nombre: ?string, id: ?string}> $parientes
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
        public readonly ?string $nacimiento = null,
        public readonly ?string $muerte = null,
        public readonly ?string $lugarNacimiento = null,
        public readonly ?string $lugarMuerte = null,
        public readonly array $parientes = [],
    ) {
    }

    public function fallecido(): bool
    {
        return $this->muerte !== null;
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
            'nacimiento' => $this->nacimiento,
            'muerte' => $this->muerte,
            'lugar_nacimiento' => $this->lugarNacimiento,
            'lugar_muerte' => $this->lugarMuerte,
            'parientes' => $this->parientes,
            'posiblemente_vivo' => !$this->fallecido(),
            'revision_humana' => true,
            'avisos' => $this->avisos,
        ];
    }
}
