<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Registro de una lápida en BillionGraves. Como {@see FindAGrave}, lee los datos estructurados de schema.org
 * (JSON-LD o microdatos) y no la estructura de la página; sin probar contra páginas reales.
 */
final class BillionGraves extends Analizador
{
    public function red(): string
    {
        return 'billiongraves';
    }

    public function dominios(): array
    {
        return ['billiongraves.com'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        $campos = self::camposDePersona($d['persona']);
        if ($campos['nombre'] === null && isset($d['meta']['og:title'])) {
            $campos['nombre'] = trim((string) preg_replace('/\s*(\(.*|[-|–].*)$/u', '', $d['meta']['og:title']));
        }
        // /grave/ana-ejemplo/12345678 → «12345678», el número del registro
        $id = null;
        if ($url !== null && preg_match('#/grave/[^/]+/(\d+)#i', $url, $m) === 1) {
            $id = $m[1];
        }
        return ['usuario' => $id] + $campos;
    }
}
