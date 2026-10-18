<?php

declare(strict_types=1);

namespace App\Repositories\POS;

use App\Repositories\SitemapRepository as Base;

/** Every catalog page: products still on sale, and every grouping of them. Orders never. */
final class SitemapRepository extends Base
{
    protected function fuentes(): array
    {
        return [
            '8' => 'SELECT id AS clave FROM productos WHERE activo',
            '7' => 'SELECT id AS clave FROM colecciones',
            '6' => 'SELECT id AS clave FROM etiquetas',
            '5' => 'SELECT id AS clave FROM atributos',
            '4' => 'SELECT id AS clave FROM grupos',
        ];
    }
}
