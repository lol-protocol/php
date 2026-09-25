<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

final class OrganizacionRepository extends Repository
{
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT o.id, o.nombre, o.tipo, o.lugar_ruta, l.nombre AS lugar_nombre
               FROM organizaciones o LEFT JOIN lugares l ON l.ruta = o.lugar_ruta
              WHERE o.id = ?',
            [$id]
        );
    }

    /** @return list<array> */
    public function miembros(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT m.rol, m.desde, m.hasta, p.id, p.nombres, p.apellidos
               FROM organizacion_miembros m JOIN personas p ON p.id = m.persona_id
              WHERE m.organizacion_id = ?
              ORDER BY p.apellidos, p.nombres, p.id',
            [$id]
        );
    }

    /** @return list<array> records held by this organization */
    public function registros(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT id, titulo, tipo, fecha FROM registros WHERE organizacion_id = ? ORDER BY fecha, id',
            [$id]
        );
    }

    /** @return list<array> */
    public function buscar(string $texto, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT id, nombre, tipo FROM organizaciones WHERE LOWER(nombre)' . self::LIKE_ESCAPED . ' ORDER BY nombre, id'
                . self::page($limit, $offset),
            [self::patron($texto)]
        );
    }
}
