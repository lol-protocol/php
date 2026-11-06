<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/** Perfil colaborativo de WikiTree. Lee los microdatos de schema.org: ver «Fuentes de personas fallecidas» en el README. */
final class WikiTree extends Analizador
{
    public function red(): string
    {
        return 'wikitree';
    }

    public function dominios(): array
    {
        return ['wikitree.com'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        $campos = self::camposDePersona($d['persona']);
        if ($campos['nombre'] === null && isset($d['meta']['og:title'])) {
            $campos['nombre'] = trim((string) preg_replace('/\s*(\(.*|\|.*)$/u', '', $d['meta']['og:title']));
        }
        // /wiki/Ejemplo-12 → «Ejemplo-12», el identificador del perfil en WikiTree
        $id = null;
        if ($url !== null && preg_match('#/wiki/([^/?\#]+)#i', $url, $m) === 1) {
            $id = rawurldecode($m[1]);
        }
        return ['usuario' => $id] + $campos;
    }
}
