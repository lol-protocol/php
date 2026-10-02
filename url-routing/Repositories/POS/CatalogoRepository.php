<?php

declare(strict_types=1);

namespace App\Repositories\POS;

use App\Repositories\Repository;

/**
 * The catalog's grouping entities — attribute, tag, collection, category —
 * share one shape: find by id, then list the active products in it.
 */
final class CatalogoRepository extends Repository
{
    private const PRODUCTO = 'SELECT ' . ProductoRepository::RESUMEN . ' FROM productos p ';

    public function atributo(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id, tipo, nombre, valor FROM atributos WHERE id = ?', [$id]);
    }

    /** @return list<array> */
    public function productosConAtributo(int $id): array
    {
        return $this->db->fetchAll(
            self::PRODUCTO . 'JOIN producto_atributos pa ON pa.producto_id = p.id
              WHERE pa.atributo_id = ? AND p.activo = ?
              ORDER BY p.nombre, p.id',
            [$id, true]
        );
    }

    public function etiqueta(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id, nombre FROM etiquetas WHERE id = ?', [$id]);
    }

    /** @return list<array> */
    public function productosConEtiqueta(int $id): array
    {
        return $this->db->fetchAll(
            self::PRODUCTO . 'JOIN producto_etiquetas pe ON pe.producto_id = p.id
              WHERE pe.etiqueta_id = ? AND p.activo = ?
              ORDER BY p.nombre, p.id',
            [$id, true]
        );
    }

    public function coleccion(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, nombre, descripcion, inicio, fin FROM colecciones WHERE id = ?',
            [$id]
        );
    }

    /** @return list<array> in the collection's curated order */
    public function productosDeColeccion(int $id): array
    {
        return $this->db->fetchAll(
            self::PRODUCTO . 'JOIN coleccion_productos cp ON cp.producto_id = p.id
              WHERE cp.coleccion_id = ? AND p.activo = ?
              ORDER BY cp.orden, p.id',
            [$id, true]
        );
    }

    public function grupo(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT g.id, g.nombre, g.padre_id, pg.nombre AS padre_nombre
               FROM grupos g LEFT JOIN grupos pg ON pg.id = g.padre_id
              WHERE g.id = ?',
            [$id]
        );
    }

    /** @return list<array> */
    public function subgrupos(int $id): array
    {
        return $this->db->fetchAll('SELECT id, nombre FROM grupos WHERE padre_id = ? ORDER BY nombre', [$id]);
    }

    /** @return list<array> products in the category or any of its direct subcategories */
    public function productosDeGrupo(int $id): array
    {
        return $this->db->fetchAll(
            self::PRODUCTO . 'WHERE p.activo = ?
                AND (p.grupo_id = ? OR p.grupo_id IN (SELECT id FROM grupos WHERE padre_id = ?))
              ORDER BY p.nombre, p.id',
            [true, $id, $id]
        );
    }

    /**
     * Name search for the listing page, by catalog type.
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function buscar(string $tipo, string $texto, int $limit = 50, int $offset = 0): array
    {
        $tabla = match ($tipo) {
            'atributo' => 'atributos',
            'etiqueta' => 'etiquetas',
            'coleccion' => 'colecciones',
            'grupo' => 'grupos',
            default => throw new \InvalidArgumentException("Not a catalog type: {$tipo}"),
        };

        return $this->db->fetchAll(
            "SELECT id, nombre FROM {$tabla} WHERE LOWER(nombre)" . self::LIKE_ESCAPED . " ORDER BY nombre, id"
                . self::page($limit, $offset),
            [self::patron($texto)]
        );
    }
}
