<?php

declare(strict_types=1);

namespace ParsingFbInMas;

final class Bluesky extends Analizador
{
    public function red(): string
    {
        return 'bluesky';
    }

    public function dominios(): array
    {
        return ['bsky.app'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo (@ana.bsky.social)»; la dirección es bsky.app/profile/ana.bsky.social
        [$nombre, $usuario] = self::nombreYUsuario($d['meta']['og:title'] ?? ($d['titulo'] ?? null), '[\w.-]+');
        if ($usuario === null && $url !== null && preg_match('#/profile/([^/?\#]+)#', $url, $m) === 1) {
            $usuario = rawurldecode($m[1]);
        }

        return [
            'usuario' => $usuario,
            'nombre' => $nombre,
            'descripcion' => $d['meta']['og:description'] ?? null,
        ];
    }
}
