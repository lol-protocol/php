<?php

declare(strict_types=1);

namespace ParsingFbInMas;

final class TikTok extends Analizador
{
    public function red(): string
    {
        return 'tiktok';
    }

    public function dominios(): array
    {
        return ['tiktok.com'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo (@ana.ejemplo) Official | TikTok»
        $nombre = null;
        $usuario = self::primerSegmento($url);
        $usuario = $usuario !== null && str_starts_with($usuario, '@') ? substr($usuario, 1) : null;
        $titulo = $d['meta']['og:title'] ?? ($d['titulo'] ?? null);
        if ($titulo !== null && preg_match('/^(.*?)\s*\(@([\w.]{1,24})\)/u', $titulo, $m) === 1) {
            $nombre = $m[1] !== '' ? $m[1] : null;
            $usuario = $m[2];
        }
        return [
            'usuario' => $usuario,
            'nombre' => $nombre,
            'descripcion' => $d['meta']['og:description'] ?? null,
        ];
    }
}
