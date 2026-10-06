<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

final class RegistroRepository extends Repository
{
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT r.id, r.titulo, r.tipo, r.fecha, r.fuente, r.url,
                    r.lugar_ruta, l.nombre AS lugar_nombre,
                    r.organizacion_id, o.nombre AS organizacion_nombre
               FROM registros r
               LEFT JOIN lugares l ON l.ruta = r.lugar_ruta
               LEFT JOIN organizaciones o ON o.id = r.organizacion_id
              WHERE r.id = ?',
            [$id]
        );
    }

    /** @return list<array> events this record documents */
    public function sucesos(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT s.id, s.tipo, s.fecha
               FROM registro_sucesos rs JOIN sucesos s ON s.id = rs.suceso_id
              WHERE rs.registro_id = ?
              ORDER BY s.fecha, s.id',
            [$id]
        );
    }

    /** @return list<array> */
    public function aportadosPor(int $usuarioId): array
    {
        return $this->db->fetchAll(
            'SELECT id, titulo, tipo, fecha FROM registros WHERE aportado_por = ? ORDER BY fecha, id',
            [$usuarioId]
        );
    }

    /** @return list<array> */
    public function buscar(string $texto, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT id, titulo, tipo, fecha FROM registros WHERE LOWER(titulo)' . self::LIKE_ESCAPED . ' ORDER BY fecha, id'
                . self::page($limit, $offset),
            [self::patron($texto)]
        );
    }
}
