<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/** Memorial de Find a Grave. Lee los microdatos de schema.org: ver «Fuentes de personas fallecidas» en el README. */
final class FindAGrave extends Analizador
{
    public function red(): string
    {
        return 'findagrave';
    }

    public function dominios(): array
    {
        return ['findagrave.com'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        $campos = self::camposDePersona($d['persona']);
        if ($campos['nombre'] === null && isset($d['meta']['og:title'])) {
            $campos['nombre'] = trim((string) preg_replace('/\s*(\(.*|[-|–].*)$/u', '', $d['meta']['og:title']));
        }
        // /memorial/12345/ana-ejemplo → «12345», el número del memorial
        $id = null;
        if ($url !== null && preg_match('#/memorial/(\d+)#i', $url, $m) === 1) {
            $id = $m[1];
        }
        return ['usuario' => $id] + $campos;
    }
}
