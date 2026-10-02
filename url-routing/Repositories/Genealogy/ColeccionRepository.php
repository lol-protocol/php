<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

final class ColeccionRepository extends Repository
{
    public function find(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT c.id, c.nombre, c.descripcion, c.usuario_id, c.publica, u.nombre AS autor,
                    (SELECT COUNT(*) FROM coleccion_personas cp WHERE cp.coleccion_id = c.id) AS total_personas
               FROM colecciones c JOIN usuarios u ON u.id = c.usuario_id
              WHERE c.id = ?',
            [$id]
        );

        if ($row !== null) {
            // SQLite returns 0/1 where PostgreSQL returns a real boolean.
            $row['publica'] = (bool)$row['publica'];
        }

        return $row;
    }

    /** @return list<array> personas in the tree, with parent ids to draw it */
    public function personas(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.nombres, p.apellidos, p.sexo, p.padre_id, p.madre_id
               FROM coleccion_personas cp JOIN personas p ON p.id = cp.persona_id
              WHERE cp.coleccion_id = ?
              ORDER BY p.apellidos, p.nombres, p.id',
            [$id]
        );
    }

    /**
     * Everything a GEDCOM export needs: personas with sex and vital dates.
     *
     * @return list<array>
     */
    public function personasParaExportar(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.nombres, p.apellidos, p.sexo, p.padre_id, p.madre_id,
                    (SELECT s.fecha FROM sucesos s JOIN suceso_participantes sp ON sp.suceso_id = s.id
                      WHERE sp.persona_id = p.id AND s.tipo = 'nacimiento' ORDER BY s.fecha LIMIT 1) AS nacimiento,
                    (SELECT l.nombre FROM sucesos s JOIN suceso_participantes sp ON sp.suceso_id = s.id
                       LEFT JOIN lugares l ON l.ruta = s.lugar_ruta
                      WHERE sp.persona_id = p.id AND s.tipo = 'nacimiento' ORDER BY s.fecha LIMIT 1) AS lugar_nacimiento,
                    (SELECT s.fecha FROM sucesos s JOIN suceso_participantes sp ON sp.suceso_id = s.id
                      WHERE sp.persona_id = p.id AND s.tipo = 'defuncion' ORDER BY s.fecha LIMIT 1) AS defuncion
               FROM coleccion_personas cp JOIN personas p ON p.id = cp.persona_id
              WHERE cp.coleccion_id = ?
              ORDER BY p.id",
            [$id]
        );
    }

    /** @return list<array> */
    public function deUsuario(int $usuarioId): array
    {
        return $this->db->fetchAll(
            'SELECT c.id, c.nombre, c.publica,
                    (SELECT COUNT(*) FROM coleccion_personas cp WHERE cp.coleccion_id = c.id) AS total_personas
               FROM colecciones c WHERE c.usuario_id = ? ORDER BY c.nombre, c.id',
            [$usuarioId]
        );
    }

    /** @return list<array> only public trees are searchable */
    public function buscar(string $texto, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT id, nombre FROM colecciones WHERE publica = ? AND LOWER(nombre)' . self::LIKE_ESCAPED . ' ORDER BY nombre, id'
                . self::page($limit, $offset),
            [true, self::patron($texto)]
        );
    }
}
