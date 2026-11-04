<?php

declare(strict_types=1);

namespace ParsingFbInMas;

final class Instagram extends Analizador
{
    public function red(): string
    {
        return 'instagram';
    }

    public function dominios(): array
    {
        return ['instagram.com', 'instagr.am'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo (@ana.ejemplo) • Fotos y videos de Instagram»
        $nombre = null;
        $usuario = null;
        $titulo = $d['meta']['og:title'] ?? ($d['titulo'] ?? null);
        if ($titulo !== null && preg_match('/^(.*?)\s*\(@([\w.]{1,30})\)/u', $titulo, $m) === 1) {
            $nombre = $m[1] !== '' ? $m[1] : null;
            $usuario = $m[2];
        }
        $usuario ??= self::primerSegmento($url);

        // «1,234 Followers, 56 Following, 78 Posts - Ana (@ana) on Instagram: "la biografía"».
        // Los contadores de seguidores no se guardan: solo la biografía, si viene.
        $descripcion = null;
        $texto = $d['meta']['og:description'] ?? null;
        if ($texto !== null
            && preg_match('/(?:on|en) Instagram:\s*[“"«](.*)[”"»]\s*$/su', $texto, $m) === 1) {
            $descripcion = $m[1];
        }

        return ['usuario' => $usuario, 'nombre' => $nombre, 'descripcion' => $descripcion];
    }
}
