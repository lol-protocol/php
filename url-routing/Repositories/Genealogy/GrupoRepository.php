<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

final class GrupoRepository extends Repository
{
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT g.id, g.apellido, g.origen, g.descripcion,
                    (SELECT COUNT(*) FROM personas p WHERE p.grupo_id = g.id) AS total_personas
               FROM grupos g WHERE g.id = ?',
            [$id]
        );
    }

    /** @return list<array> the surname's personas with parent ids (its family network) */
    public function red(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.nombres, p.apellidos, p.sexo, p.padre_id, p.madre_id
               FROM personas p WHERE p.grupo_id = ?
              ORDER BY p.apellidos, p.nombres, p.id',
            [$id]
        );
    }

    /**
     * Where the surname's bearers were born, counted per place.
     *
     * @return list<array{lugar_ruta: string, lugar_nombre: string, total: int}>
     */
    public function dispersion(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT s.lugar_ruta, l.nombre AS lugar_nombre, COUNT(DISTINCT p.id) AS total
               FROM personas p
               JOIN suceso_participantes sp ON sp.persona_id = p.id
               JOIN sucesos s ON s.id = sp.suceso_id AND s.tipo = 'nacimiento'
               JOIN lugares l ON l.ruta = s.lugar_ruta
              WHERE p.grupo_id = ?
              GROUP BY s.lugar_ruta, l.nombre
              ORDER BY total DESC, s.lugar_ruta",
            [$id]
        );
    }

    /** @return list<array> */
    public function buscar(string $texto, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT id, apellido FROM grupos WHERE LOWER(apellido)' . self::LIKE_ESCAPED . ' ORDER BY apellido, id'
                . self::page($limit, $offset),
            [self::patron($texto)]
        );
    }
}
