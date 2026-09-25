<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

final class PersonaRepository extends Repository
{
    /** Generations walked by ascendencia/descendencia; also a cycle guard. */
    public const MAX_GENERACIONES = 8;

    /** Birth and death come from the persona's events, the single source of truth for dates. */
    private const RESUMEN = "p.id, p.nombres, p.apellidos, p.sexo,
        (SELECT s.fecha FROM sucesos s JOIN suceso_participantes sp ON sp.suceso_id = s.id
          WHERE sp.persona_id = p.id AND s.tipo = 'nacimiento' ORDER BY s.fecha LIMIT 1) AS nacimiento,
        (SELECT s.fecha FROM sucesos s JOIN suceso_participantes sp ON sp.suceso_id = s.id
          WHERE sp.persona_id = p.id AND s.tipo = 'defuncion' ORDER BY s.fecha LIMIT 1) AS defuncion";

    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT ' . self::RESUMEN . ",
                    (SELECT s.lugar_ruta FROM sucesos s JOIN suceso_participantes sp ON sp.suceso_id = s.id
                      WHERE sp.persona_id = p.id AND s.tipo = 'nacimiento' ORDER BY s.fecha LIMIT 1) AS lugar_nacimiento,
                    p.padre_id, p.madre_id, p.grupo_id, g.apellido AS grupo_apellido
               FROM personas p
               LEFT JOIN grupos g ON g.id = p.grupo_id
              WHERE p.id = ?",
            [$id]
        );
    }

    /** @return list<array> ancestors with their generation (1 = parents) */
    public function ascendencia(int $id, int $maxGeneraciones = self::MAX_GENERACIONES): array
    {
        return $this->db->fetchAll(
            'WITH RECURSIVE ancestros (id, generacion) AS (
                 SELECT p.id, 1
                   FROM personas h JOIN personas p ON p.id IN (h.padre_id, h.madre_id)
                  WHERE h.id = ?
                 UNION ALL
                 SELECT p.id, a.generacion + 1
                   FROM ancestros a
                   JOIN personas h ON h.id = a.id
                   JOIN personas p ON p.id IN (h.padre_id, h.madre_id)
                  WHERE a.generacion < ?
             )
             SELECT a.generacion, ' . self::RESUMEN . '
               FROM (SELECT id, MIN(generacion) AS generacion FROM ancestros GROUP BY id) a
               JOIN personas p ON p.id = a.id
              ORDER BY a.generacion, p.sexo DESC, p.id',
            [$id, $maxGeneraciones]
        );
    }

    /** @return list<array> descendants with their generation (1 = children) */
    public function descendencia(int $id, int $maxGeneraciones = self::MAX_GENERACIONES): array
    {
        return $this->db->fetchAll(
            'WITH RECURSIVE descendientes (id, generacion) AS (
                 SELECT p.id, 1 FROM personas p WHERE ? IN (p.padre_id, p.madre_id)
                 UNION ALL
                 SELECT p.id, d.generacion + 1
                   FROM descendientes d
                   JOIN personas p ON d.id IN (p.padre_id, p.madre_id)
                  WHERE d.generacion < ?
             )
             SELECT d.generacion, ' . self::RESUMEN . '
               FROM (SELECT id, MIN(generacion) AS generacion FROM descendientes GROUP BY id) d
               JOIN personas p ON p.id = d.id
              ORDER BY d.generacion, nacimiento, p.id',
            [$id, $maxGeneraciones]
        );
    }

    /**
     * Direct relatives: parents, children, siblings (sharing at least one
     * parent) and recorded links (spouses, godparents...), in both directions.
     *
     * @return list<array> each row has a "relacion" column
     */
    public function vinculos(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT r.relacion, " . self::RESUMEN . "
               FROM (
                   SELECT 'padre' AS relacion, h.padre_id AS id FROM personas h WHERE h.id = ? AND h.padre_id IS NOT NULL
                   UNION ALL
                   SELECT 'madre', h.madre_id FROM personas h WHERE h.id = ? AND h.madre_id IS NOT NULL
                   UNION ALL
                   SELECT 'hijo', p.id FROM personas p WHERE ? IN (p.padre_id, p.madre_id)
                   UNION ALL
                   SELECT DISTINCT 'hermano', p.id
                     FROM personas h JOIN personas p
                       ON (p.padre_id = h.padre_id OR p.madre_id = h.madre_id)
                    WHERE h.id = ? AND p.id <> h.id
                   UNION ALL
                   SELECT v.tipo, v.persona_b FROM vinculos v WHERE v.persona_a = ?
                   UNION ALL
                   SELECT v.tipo, v.persona_a FROM vinculos v WHERE v.persona_b = ?
               ) r
               JOIN personas p ON p.id = r.id
              ORDER BY CASE r.relacion WHEN 'padre' THEN 1 WHEN 'madre' THEN 2 WHEN 'conyuge' THEN 3
                                       WHEN 'hermano' THEN 4 WHEN 'hijo' THEN 5 ELSE 6 END,
                       nacimiento, p.id",
            [$id, $id, $id, $id, $id, $id]
        );
    }

    /** @return list<array> the persona's events in date order */
    public function cronologia(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT s.id, s.tipo, s.fecha, s.descripcion, sp.rol, s.lugar_ruta, l.nombre AS lugar_nombre
               FROM suceso_participantes sp
               JOIN sucesos s ON s.id = sp.suceso_id
               LEFT JOIN lugares l ON l.ruta = s.lugar_ruta
              WHERE sp.persona_id = ?
              ORDER BY s.fecha, s.id',
            [$id]
        );
    }

    /** @return list<array> */
    public function buscar(string $texto, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT ' . self::RESUMEN . "
               FROM personas p
              WHERE LOWER(p.nombres || ' ' || p.apellidos)" . self::LIKE_ESCAPED . "
              ORDER BY p.apellidos, p.nombres, p.id" . self::page($limit, $offset),
            [self::patron($texto)]
        );
    }

    /** @return list<array> personas contributed by a user */
    public function aportadasPor(int $usuarioId): array
    {
        return $this->db->fetchAll(
            'SELECT ' . self::RESUMEN . ' FROM personas p WHERE p.aportado_por = ? ORDER BY p.creado_en DESC, p.id',
            [$usuarioId]
        );
    }
}
