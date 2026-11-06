<?php

declare(strict_types=1);

namespace ParsingFbInMas;

final class Pinterest extends Analizador
{
    public function red(): string
    {
        return 'pinterest';
    }

    public function dominios(): array
    {
        return ['pinterest.com', 'pin.it'];
    }

    protected function reservados(): array
    {
        return ['pin', 'search', 'ideas', 'today', 'business', 'login', 'explore'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo (anaejemplo) - Perfil | Pinterest»
        [$nombre, $usuario] = self::nombreYUsuario($d['meta']['og:title'] ?? ($d['titulo'] ?? null), '\w{3,30}', false);

        return [
            'usuario' => $usuario ?? $this->usuarioDeRuta($url),
            'nombre' => $nombre,
            'descripcion' => $d['meta']['og:description'] ?? null,
        ];
    }
}
