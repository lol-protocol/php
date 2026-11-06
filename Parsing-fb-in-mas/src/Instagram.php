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

    protected function reservados(): array
    {
        return ['p', 'reel', 'reels', 'tv', 'explore', 'stories', 'accounts', 'direct', 'about', 'web'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo (@ana.ejemplo) • Fotos y videos de Instagram»
        [$nombre, $usuario] = self::nombreYUsuario($d['meta']['og:title'] ?? ($d['titulo'] ?? null));
        $usuario ??= $this->usuarioDeRuta($url);

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
