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

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo (anaejemplo) - Perfil | Pinterest»
        $nombre = null;
        $usuario = self::primerSegmento($url);
        $titulo = $d['meta']['og:title'] ?? ($d['titulo'] ?? null);
        if ($titulo !== null && preg_match('/^(.*?)\s*\((\w{3,30})\)/u', $titulo, $m) === 1) {
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
