<?php

declare(strict_types=1);

namespace App;

/**
 * Los mensajes cortos que una pantalla muestra arriba (views/_avisos.php):
 * un aviso cuando lo pedido por URL no se pudo respetar (un periodo que no
 * existe, un rango al reves) y, en las pantallas de destino de un alta, una
 * edicion o una anulacion, la confirmacion de que se hizo. Cada aviso es
 * ['tipo' => ..., 'texto' => ...]: el tipo solo decide el color.
 */
final class Avisos
{
    public const OK = 'ok';
    public const ATENCION = 'atencion';

    /** @return array{tipo: string, texto: string} */
    public static function ok(string $texto): array
    {
        return ['tipo' => self::OK, 'texto' => $texto];
    }

    /** @return array{tipo: string, texto: string} */
    public static function atencion(string $texto): array
    {
        return ['tipo' => self::ATENCION, 'texto' => $texto];
    }
}
