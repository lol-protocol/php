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

    /**
     * @return array<string, mixed>|null
     */
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

    /**
     * @return array<string, mixed>|null
     */
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

    /**
     * @return array<string, mixed>|null
     */
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

    /**
     * @return array<string, mixed>|null
     */
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

    /**
     * Products in the category or any of its direct subcategories, narrowed by each filter given: an atributo
     * and/or an etiqueta the product has.
     *
     * @param array<string, int|string> $filtros type (atributo, etiqueta) => id
     * @return list<array>
     */
    public function productosDeGrupo(int $id, array $filtros = []): array
    {
        $donde = '';
        $params = [true, $id, $id];
        foreach ($filtros as $tipo => $filtro) {
            $donde .= match ($tipo) {
                'atributo' => ' AND EXISTS (SELECT 1 FROM producto_atributos f WHERE f.producto_id = p.id AND f.atributo_id = ?)',
                'etiqueta' => ' AND EXISTS (SELECT 1 FROM producto_etiquetas f WHERE f.producto_id = p.id AND f.etiqueta_id = ?)',
                default => throw new \InvalidArgumentException("Not a filter of a category: {$tipo}"),
            };
            $params[] = (int)$filtro;
        }

        return $this->db->fetchAll(
            self::PRODUCTO . 'WHERE p.activo = ?
                AND (p.grupo_id = ? OR p.grupo_id IN (SELECT id FROM grupos WHERE padre_id = ?))' . $donde . '
              ORDER BY p.nombre, p.id',
            $params
        );
    }

    /**
     * The filters worth offering in a category: the atributos and etiquetas that some of its active products have
     * (the same products productosDeGrupo() lists), so no filter leads to an empty page.
     *
     * @return array{atributo: list<array<string, mixed>>, etiqueta: list<array<string, mixed>>}
     */
    public function filtrosDeGrupo(int $id): array
    {
        $deLaCategoria = 'p.activo = ? AND (p.grupo_id = ? OR p.grupo_id IN (SELECT id FROM grupos WHERE padre_id = ?))';

        return [
            'atributo' => $this->db->fetchAll(
                "SELECT DISTINCT a.id, a.tipo, a.nombre FROM atributos a
                   JOIN producto_atributos f ON f.atributo_id = a.id JOIN productos p ON p.id = f.producto_id
                  WHERE {$deLaCategoria} ORDER BY a.tipo, a.nombre, a.id",
                [true, $id, $id]
            ),
            'etiqueta' => $this->db->fetchAll(
                "SELECT DISTINCT e.id, e.nombre FROM etiquetas e
                   JOIN producto_etiquetas f ON f.etiqueta_id = e.id JOIN productos p ON p.id = f.producto_id
                  WHERE {$deLaCategoria} ORDER BY e.nombre, e.id",
                [true, $id, $id]
            ),
        ];
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
            "SELECT id, nombre FROM {$tabla} WHERE " . self::sinAcentos('nombre') . self::LIKE_ESCAPED . " ORDER BY nombre, id"
                . self::page($limit, $offset),
            [self::patron($texto)]
        );
    }
}
