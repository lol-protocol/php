<?php

declare(strict_types=1);

namespace ParsingFbInMas;

final class GitHub extends Analizador
{
    public function red(): string
    {
        return 'github';
    }

    public function dominios(): array
    {
        return ['github.com'];
    }

    protected function reservados(): array
    {
        return ['orgs', 'settings', 'topics', 'marketplace', 'explore', 'features', 'pricing', 'login', 'search', 'sponsors', 'notifications', 'pulls', 'issues', 'collections', 'trending', 'about', 'new', 'enterprise', 'apps'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        // «octocat (Ana Ejemplo)» o solo «octocat»
        $nombre = null;
        $usuario = $this->usuarioDeRuta($url);
        $titulo = $d['meta']['og:title'] ?? null;
        if ($titulo !== null) {
            $titulo = (string) preg_replace('/\s*[·|]\s*GitHub\s*$/u', '', $titulo);
            if (preg_match('/^(\S+)\s+\((.+)\)$/u', $titulo, $m) === 1) {
                $usuario = $m[1];
                $nombre = $m[2];
            }
        }
        // «Biografía - octocat has 8 repositories available. Follow their code on GitHub.»
        $desc = $d['meta']['og:description'] ?? null;
        if ($desc !== null) {
            $desc = (string) preg_replace('/\s*[-–]?\s*\S+ has \d+ repositor(?:y|ies) available\.\s*Follow their code on GitHub\.\s*$/u', '', $desc);
        }
        return ['usuario' => $usuario, 'nombre' => $nombre, 'descripcion' => $desc];
    }
}
