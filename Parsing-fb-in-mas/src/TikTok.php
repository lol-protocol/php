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
        [$nombre, $usuario] = self::nombreYUsuario($d['meta']['og:title'] ?? ($d['titulo'] ?? null), '[\w.]{1,24}');

        return [
            'usuario' => $usuario ?? self::usuarioConArroba($url),
            'nombre' => $nombre,
            'descripcion' => $d['meta']['og:description'] ?? null,
        ];
    }
}
