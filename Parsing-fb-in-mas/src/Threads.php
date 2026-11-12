<?php

declare(strict_types=1);

namespace ParsingFbInMas;

final class Threads extends Analizador
{
    public function red(): string
    {
        return 'threads';
    }

    public function dominios(): array
    {
        return ['threads.net', 'threads.com'];
    }

    protected function extraer(array $d, ?string $url): array
    {
        // «Ana Ejemplo (@ana.ejemplo) • Threads, Say more»
        [$nombre, $usuario] = self::nombreYUsuario($d['meta']['og:title'] ?? ($d['titulo'] ?? null));

        return [
            'usuario' => $usuario ?? self::usuarioConArroba($url),
            'nombre' => $nombre,
            'descripcion' => $d['meta']['og:description'] ?? null,
        ];
    }
}
