<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Perfil de Mastodon o de cualquier servidor del fediverso que use su misma página. No hay un dominio
 * que lo identifique (cada instancia tiene el suyo), así que se reconoce por la forma del título:
 * «Ana Ejemplo (@ana@mastodon.social)».
 */
final class Mastodon extends Analizador
{
    private const TITULO = '/\(@([\w.]+)@([\w-]+(?:\.[\w-]+)+)\)/u';

    public function red(): string
    {
        return 'mastodon';
    }

    public function dominios(): array
    {
        return [];
    }

    public function reconoce(array $d): bool
    {
        return preg_match(self::TITULO, $d['meta']['og:title'] ?? ($d['titulo'] ?? '')) === 1;
    }

    protected function extraer(array $d, ?string $url): array
    {
        $titulo = $d['meta']['og:title'] ?? ($d['titulo'] ?? null);
        [$nombre] = self::nombreYUsuario($titulo, '[\w.]+@[\w.-]+');
        $usuario = $titulo !== null && preg_match(self::TITULO, $titulo, $m) === 1 ? $m[1] . '@' . $m[2] : null;

        return [
            'usuario' => $usuario,
            'nombre' => $nombre,
            'descripcion' => $d['meta']['og:description'] ?? null,
        ];
    }
}
