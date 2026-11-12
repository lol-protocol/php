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
    public readonly ?string $nacimiento;
    public readonly ?string $muerte;
    public readonly ?string $lugarNacimiento;
    public readonly ?string $lugarMuerte;
    /** @var list<array{relacion: string, nombre: ?string, id: ?string}> */
    public readonly array $parientes;
    /** @var list<string> */
    public readonly array $marcas;

    /**
     * La regla «fechas, lugares y parientes solo si consta una muerte» se cumple aquí y en
     * ningún otro sitio: sin `$muerte`, esos cinco datos se descartan, lleguen de donde lleguen.
     *
     * @param list<string> $avisos
     * @param list<array{relacion: string, nombre: ?string, id: ?string}> $parientes
     * @param list<string> $marcas lo que {@see Revision} le encontró al texto (titular y descripción)
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
        ?string $nacimiento = null,
        ?string $muerte = null,
        ?string $lugarNacimiento = null,
        ?string $lugarMuerte = null,
        array $parientes = [],
        array $marcas = [],
    ) {
        $consta = $muerte !== null;
        $this->muerte = $muerte;
        $this->nacimiento = $consta ? $nacimiento : null;
        $this->lugarNacimiento = $consta ? $lugarNacimiento : null;
        $this->lugarMuerte = $consta ? $lugarMuerte : null;
        $this->parientes = $consta ? $parientes : [];
        $this->marcas = $marcas;
    }

    public function fallecido(): bool
    {
        return $this->muerte !== null;
    }

    /**
     * @return array{red: string, url: ?string, usuario: ?string, nombre: ?string, titular: ?string, lugar: ?string, descripcion: ?string, capturado: ?string, nacimiento: ?string, muerte: ?string, lugar_nacimiento: ?string, lugar_muerte: ?string, parientes: list<array{relacion: string, nombre: ?string, id: ?string}>, marcas: list<string>, posiblemente_vivo: bool, revision_humana: bool, avisos: list<string>}
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
            'marcas' => $this->marcas,
            'posiblemente_vivo' => !$this->fallecido(),
            'revision_humana' => true,
            'avisos' => $this->avisos,
        ];
    }
}
