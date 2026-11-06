<?php

declare(strict_types=1);

namespace ParsingFbInMas;

final class Facebook extends Analizador
{
    public function red(): string
    {
        return 'facebook';
    }

    public function dominios(): array
    {
        return ['facebook.com', 'fb.com'];
    }

    protected function reservados(): array
    {
        return ['people', 'pages', 'p', 'groups', 'events', 'watch', 'marketplace', 'photo', 'photos', 'reel', 'reels', 'share', 'sharer', 'login', 'hashtag', 'story.php', 'permalink.php', 'stories', 'gaming', 'public'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        $nombre = $d['meta']['og:title'] ?? ($d['persona']['nombre'] ?? null);
        if ($nombre !== null) {
            $nombre = (string) preg_replace('/\s*[|\-–]\s*(Facebook|Inicio|Home)\s*$/iu', '', $nombre);
        }

        // «1.234 likes · 5 talking about this. Texto…» / «1.234 Me gusta · 5 personas…»
        $descripcion = $d['meta']['og:description'] ?? ($d['persona']['descripcion'] ?? null);
        if ($descripcion !== null) {
            $descripcion = (string) preg_replace('/^[\d.,\s]+(likes?|me gusta)[^.]*\.\s*/iu', '', $descripcion);
        }

        $usuario = $this->usuarioDeRuta($url);
        // /people/Ana-Ejemplo/100000000000001 es un perfil sin nombre de usuario: va por su número.
        if ($url !== null && preg_match('#/people/[^/]+/(\d+)#', $url, $m) === 1) {
            $usuario = 'id:' . $m[1];
        }
        if ($usuario === 'profile.php') {
            parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $consulta);
            $id = $consulta['id'] ?? null;
            $usuario = is_string($id) && ctype_digit($id) ? 'id:' . $id : null;
        }

        return [
            'usuario' => $usuario,
            'nombre' => $nombre,
            'titular' => $d['persona']['titular'] ?? null,
            'lugar' => $d['persona']['lugar'] ?? null,
            'descripcion' => $descripcion,
        ];
    }
}
