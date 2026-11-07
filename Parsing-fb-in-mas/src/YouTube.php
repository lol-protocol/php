<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/** Canal de YouTube (no videos). */
final class YouTube extends Analizador
{
    public function red(): string
    {
        return 'youtube';
    }

    public function dominios(): array
    {
        return ['youtube.com', 'youtu.be'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        return [
            'usuario' => self::usuarioConArroba($url),
            'nombre' => $d['meta']['og:title'] ?? ($d['persona']['nombre'] ?? null),
            'descripcion' => $d['meta']['og:description'] ?? null,
        ];
    }
}
