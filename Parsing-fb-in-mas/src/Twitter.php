<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/** Twitter, hoy X: el mismo servicio con dos dominios. */
final class Twitter extends Analizador
{
    public function red(): string
    {
        return 'twitter';
    }

    public function dominios(): array
    {
        return ['twitter.com', 'x.com'];
    }

    protected function reservados(): array
    {
        return ['home', 'i', 'search', 'explore', 'hashtag', 'intent', 'share', 'settings', 'login', 'notifications', 'messages', 'compose', 'tos', 'privacy'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo (@anaejemplo) / X», «Ana Ejemplo (@anaejemplo) on Twitter»
        [$nombre, $usuario] = self::nombreYUsuario($d['meta']['og:title'] ?? ($d['meta']['twitter:title'] ?? ($d['titulo'] ?? null)), '\w{1,15}');

        return [
            'usuario' => $usuario ?? $this->usuarioDeRuta($url),
            'nombre' => $nombre,
            'descripcion' => $d['meta']['og:description'] ?? ($d['meta']['twitter:description'] ?? null),
        ];
    }
}
