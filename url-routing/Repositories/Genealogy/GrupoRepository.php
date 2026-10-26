<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;
use App\Support\Privacidad;

final class GrupoRepository extends Repository
{
    /**
     * SQL: the personas (aliased p, from personas()) whose REAL grupo_id is $grupo, a column or a `?`, picked on the
     * real table. personas() masks grupo_id for a hidden persona, so a filter on its column alone leaves the database
     * to work out `oculta` for the whole catalog before it can tell who is in the group; this picks the group's
     * members first and personas() masks only those, as PersonaRepository::buscar() does. It never replaces
     * `p.grupo_id = $grupo`, which stays in every query: that is what leaves the hidden ones out.
     */
    private static function delGrupo(string $grupo): string
    {
        return "p.id IN (SELECT q.id FROM personas q WHERE q.grupo_id = {$grupo})";
    }

    /**
     * A living persona's surname group is hidden (see Privacidad::personas()),
     * so living people count in no group, appear in no group's network and
     * weigh in no group's birthplaces.
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT g.id, g.apellido, g.origen, g.descripcion,
                    (SELECT COUNT(*) FROM {$this->privacidad->personas()} p WHERE " . self::delGrupo('g.id') . " AND p.grupo_id = g.id) AS total_personas
               FROM grupos g WHERE g.id = ?",
            [$id]
        );
    }

    /** @return list<array> the surname's personas with parent ids (its family network) */
    public function red(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.nombres, p.apellidos, p.sexo, p.padre_id, p.madre_id
               FROM {$this->privacidad->personas()} p WHERE " . self::delGrupo('?') . " AND p.grupo_id = ?
              ORDER BY p.apellidos, p.nombres, p.id",
            [$id, $id]
        );
    }

    /**
     * Where the surname's bearers were born, counted per place. A birth with a
     * living participant counts nowhere (see Privacidad::sucesos()).
     *
     * @return list<array{lugar_ruta: string, lugar_nombre: string, total: int}>
     */
    public function dispersion(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT s.lugar_ruta, l.nombre AS lugar_nombre, COUNT(DISTINCT p.id) AS total
               FROM {$this->privacidad->personas()} p
               JOIN suceso_participantes sp ON sp.persona_id = p.id AND sp.rol = '" . Privacidad::ROL_PRINCIPAL . "'
               JOIN {$this->privacidad->sucesos()} s ON s.id = sp.suceso_id AND s.tipo = 'nacimiento'
               JOIN lugares l ON l.ruta = s.lugar_ruta
              WHERE " . self::delGrupo('?') . " AND p.grupo_id = ?
              GROUP BY s.lugar_ruta, l.nombre
              ORDER BY total DESC, s.lugar_ruta",
            [$id, $id]
        );
    }

    /** @return list<array> */
    public function buscar(string $texto, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT id, apellido FROM grupos WHERE ' . self::sinAcentos('apellido') . self::LIKE_ESCAPED
                . $this->sinRetiradas('grupo', 'grupos.id') . ' ORDER BY apellido, id'
                . self::page($limit, $offset),
            [self::patron($texto)]
        );
    }
}
