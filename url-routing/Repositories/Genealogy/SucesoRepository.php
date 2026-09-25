<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

final class SucesoRepository extends Repository
{
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT s.id, s.tipo, s.fecha, s.descripcion, s.lugar_ruta, l.nombre AS lugar_nombre
               FROM sucesos s LEFT JOIN lugares l ON l.ruta = s.lugar_ruta
              WHERE s.id = ?',
            [$id]
        );
    }

    /** @return list<array> */
    public function participantes(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT sp.rol, p.id, p.nombres, p.apellidos
               FROM suceso_participantes sp JOIN personas p ON p.id = sp.persona_id
              WHERE sp.suceso_id = ?
              ORDER BY sp.rol, p.apellidos, p.nombres',
            [$id]
        );
    }

    /** @return list<array> records that document this event */
    public function registros(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT r.id, r.titulo, r.tipo, r.fecha
               FROM registro_sucesos rs JOIN registros r ON r.id = rs.registro_id
              WHERE rs.suceso_id = ?
              ORDER BY r.fecha, r.id',
            [$id]
        );
    }

    /** @return list<array> */
    public function buscar(string $texto, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT s.id, s.tipo, s.fecha, l.nombre AS lugar_nombre
               FROM sucesos s LEFT JOIN lugares l ON l.ruta = s.lugar_ruta
              WHERE LOWER(s.tipo || ' ' || COALESCE(s.descripcion, '') || ' ' || COALESCE(l.nombre, ''))" . self::LIKE_ESCAPED . "
              ORDER BY s.fecha, s.id" . self::page($limit, $offset),
            [self::patron($texto)]
        );
    }
}
