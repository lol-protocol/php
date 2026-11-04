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
        $seg = self::primerSegmento($url);
        return [
            'usuario' => $seg !== null && str_starts_with($seg, '@') ? substr($seg, 1) : null,
            'nombre' => $d['meta']['og:title'] ?? ($d['persona']['nombre'] ?? null),
            'descripcion' => $d['meta']['og:description'] ?? null,
        ];
    }
}
