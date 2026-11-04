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

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo (@anaejemplo) / X», «Ana Ejemplo (@anaejemplo) on Twitter»
        $nombre = null;
        $usuario = null;
        $titulo = $d['meta']['og:title'] ?? ($d['meta']['twitter:title'] ?? ($d['titulo'] ?? null));
        if ($titulo !== null && preg_match('/^(.*?)\s*\(@(\w{1,15})\)/u', $titulo, $m) === 1) {
            $nombre = $m[1];
            $usuario = $m[2];
        }
        $usuario ??= self::primerSegmento($url);

        return [
            'usuario' => $usuario,
            'nombre' => $nombre,
            'descripcion' => $d['meta']['og:description'] ?? ($d['meta']['twitter:description'] ?? null),
        ];
    }
}
