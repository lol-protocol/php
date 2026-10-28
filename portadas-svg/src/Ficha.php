<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Textos de la contratapa y de las solapas armados solo con los datos del libro. No resume la obra ni
 * inventa nada: dice qué es, de quién, de qué época y tradición, y en qué categoría se clasifica. Quien
 * quiera una sinopsis la escribe en `contratapa` o en las solapas, y eso manda sobre esta ficha.
 */
final class Ficha
{
    /**
     * @param array{slug: string, nombre: string}|null $tradicion
     * @param array{slug: string, nombre: string, familia: string}|null $categoria
     */
    public function __construct(
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly string $autor,
        private readonly string $traductor,
        private readonly string $epoca,
        private readonly string $cita,
        private readonly ?array $tradicion,
        private readonly ?array $categoria,
        private readonly ?int $paginas,
    ) {
    }

    /** Sin época, tradición, categoría ni cita no hay nada que añadir a título y autor: no se arma ficha. */
    public function hayDatos(): bool
    {
        return $this->epoca !== '' || $this->cita !== '' || $this->tradicion !== null || $this->categoria !== null;
    }

    /** Qué obra es y de quién, en qué categoría se clasifica, y su traducción y extensión. La cita no va aquí: la contratapa la compone aparte. */
    public function contratapa(): string
    {
        return self::parrafos([
            $this->obra(),
            $this->clasificacion(),
            self::unir([$this->traduccion(), $this->extension()]),
        ]);
    }

    /** La edición: clasificación, traducción y extensión. */
    public function solapaPortada(): string
    {
        return self::parrafos([
            $this->clasificacion(),
            self::unir([$this->traduccion(), $this->extension()]),
        ]);
    }

    /** El autor: nombre, época y tradición. */
    public function solapaContratapa(): string
    {
        return self::parrafos([
            $this->autor . ($this->epoca === '' ? '' : " ({$this->epoca})") . '.',
            $this->tradicion === null ? '' : "Tradición {$this->tradicion['nombre']}.",
        ]);
    }

    private function obra(): string
    {
        $nombre = $this->subtitulo === '' ? $this->titulo : "{$this->titulo}: {$this->subtitulo}";
        $epoca = $this->epoca === '' ? '' : " ({$this->epoca})";
        $tradicion = $this->tradicion === null ? '' : ", de la tradición {$this->tradicion['nombre']}";

        return "«{$nombre}» es una obra de {$this->autor}{$epoca}{$tradicion}.";
    }

    private function clasificacion(): string
    {
        if ($this->categoria === null) {
            return '';
        }

        return mb_strtoupper(mb_substr($this->categoria['familia'], 0, 1)) . mb_substr($this->categoria['familia'], 1) . ": {$this->categoria['nombre']}.";
    }

    private function traduccion(): string
    {
        return $this->traductor === '' ? '' : "Traducción de {$this->traductor}.";
    }

    private function extension(): string
    {
        return $this->paginas === null ? '' : "{$this->paginas} páginas.";
    }

    /** @param list<string> $partes */
    private static function unir(array $partes): string
    {
        return implode(' ', array_filter($partes, static fn (string $parte): bool => $parte !== ''));
    }

    /** @param list<string> $parrafos los vacíos se saltan */
    private static function parrafos(array $parrafos): string
    {
        return implode("\n\n", array_filter($parrafos, static fn (string $parrafo): bool => $parrafo !== ''));
    }
}
