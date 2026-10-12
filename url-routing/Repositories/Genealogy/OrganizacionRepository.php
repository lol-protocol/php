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

    /**
     * Members, except living people: their role and dates would say who they
     * are even with the name hidden.
     *
     * @return list<array>
     */
    public function miembros(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT m.rol, m.desde, m.hasta, p.id, p.nombres, p.apellidos
               FROM organizacion_miembros m JOIN {$this->privacidad->personas()} p ON p.id = m.persona_id
              WHERE m.organizacion_id = ? AND NOT p.oculta
              ORDER BY p.apellidos, p.nombres, p.id",
            [$id]
        );
    }

    /**
     * Records held by this organization, except those that document an event
     * with a living participant (see Privacidad::registros()).
     *
     * @return list<array>
     */
    public function registros(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT r.id, r.titulo, r.tipo, r.fecha FROM {$this->privacidad->registros()} r
              WHERE r.organizacion_id = ? ORDER BY r.fecha, r.id",
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
