<?php

declare(strict_types=1);

namespace ParsingFbInMas;

final class LinkedIn extends Analizador
{
    public function red(): string
    {
        return 'linkedin';
    }

    public function dominios(): array
    {
        return ['linkedin.com'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo - Ingeniera - Acme | LinkedIn»
        $titulo = $d['meta']['og:title'] ?? ($d['titulo'] ?? null);
        $nombre = $d['persona']['nombre'] ?? null;
        $titular = $d['persona']['titular'] ?? null;
        if ($titulo !== null) {
            $titulo = (string) preg_replace('/\s*\|\s*LinkedIn\s*$/iu', '', $titulo);
            $partes = preg_split('/\s+[-–]\s+/u', $titulo) ?: [];
            $nombre ??= $partes[0] ?? null;
            if ($titular === null && count($partes) > 1) {
                $titular = implode(' - ', array_slice($partes, 1));
            }
        }

        // «Ubicación: Santiago, Chile · 500+ contactos · …»
        $descripcion = $d['meta']['og:description'] ?? ($d['persona']['descripcion'] ?? null);
        $lugar = $d['persona']['lugar'] ?? null;
        if ($lugar === null && $descripcion !== null
            && preg_match('/(?:Ubicaci[oó]n|Location)\s*:\s*([^·|]+)/iu', $descripcion, $m) === 1) {
            $lugar = trim($m[1]);
        }

        $usuario = null;
        if ($url !== null && preg_match('#/in/([^/?\#]+)#i', $url, $m) === 1) {
            $usuario = rawurldecode($m[1]);
        }

        return [
            'usuario' => $usuario,
            'nombre' => $nombre,
            'titular' => $titular,
            'lugar' => $lugar,
            'descripcion' => $descripcion,
        ];
    }
}
