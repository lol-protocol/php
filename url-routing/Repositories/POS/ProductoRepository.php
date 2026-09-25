<?php

declare(strict_types=1);

namespace App\Repositories\POS;

use App\Repositories\Repository;

final class ProductoRepository extends Repository
{
    public const RESUMEN = 'p.id, p.nombre, p.precio_centavos, p.moneda';

    /** Returns discontinued products too — the page says so instead of 404ing old links. */
    public function find(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT p.id, p.nombre, p.descripcion, p.precio_centavos, p.moneda, p.activo,
                    p.grupo_id, g.nombre AS grupo_nombre
               FROM productos p LEFT JOIN grupos g ON g.id = p.grupo_id
              WHERE p.id = ?',
            [$id]
        );

        if ($row !== null) {
            $row['activo'] = (bool)$row['activo'];
        }

        return $row;
    }

    /** @return list<array> variants with their effective price and stock */
    public function variantes(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT v.sku, v.nombre, v.stock,
                    COALESCE(v.precio_centavos, p.precio_centavos) AS precio_centavos, p.moneda
               FROM variantes v JOIN productos p ON p.id = v.producto_id
              WHERE v.producto_id = ?
              ORDER BY v.sku',
            [$id]
        );
    }

    /** @return list<array> the product's attributes, grouped by type in order */
    public function atributos(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT a.id, a.tipo, a.nombre, a.valor
               FROM producto_atributos pa JOIN atributos a ON a.id = pa.atributo_id
              WHERE pa.producto_id = ?
              ORDER BY a.tipo, a.id',
            [$id]
        );
    }

    /** @return list<array> */
    public function etiquetas(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT e.id, e.nombre
               FROM producto_etiquetas pe JOIN etiquetas e ON e.id = pe.etiqueta_id
              WHERE pe.producto_id = ?
              ORDER BY e.nombre',
            [$id]
        );
    }

    /** @return list<array> active products whose name matches */
    public function buscar(string $texto, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT ' . self::RESUMEN . ' FROM productos p
              WHERE p.activo = ? AND LOWER(p.nombre)' . self::LIKE_ESCAPED . '
              ORDER BY p.nombre, p.id' . self::page($limit, $offset),
            [true, self::patron($texto)]
        );
    }
}
