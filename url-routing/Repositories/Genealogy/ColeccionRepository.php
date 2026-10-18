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

    /**
     * Personas in the tree, with parent ids to draw it. A living persona stays
     * in the tree, hidden (see Privacidad), so the tree keeps its shape.
     *
     * @return list<array>
     */
    public function personas(int $id): array
    {
        return array_map(self::normalizar(...), $this->db->fetchAll(
            "SELECT p.id, p.nombres, p.apellidos, p.sexo, p.padre_id, p.madre_id, p.oculta
               FROM coleccion_personas cp JOIN {$this->privacidad->personas()} p ON p.id = cp.persona_id
              WHERE cp.coleccion_id = ?
              ORDER BY p.oculta, p.apellidos, p.nombres, p.id",
            [$id]
        ));
    }

    /**
     * Everything a GEDCOM export needs: personas with sex and vital dates. A
     * living persona is exported as a hidden one, with no dates or place.
     *
     * @return list<array>
     */
    public function personasParaExportar(int $id): array
    {
        return array_map(self::normalizar(...), $this->db->fetchAll(
            'SELECT p.id, p.nombres, p.apellidos, p.sexo, p.padre_id, p.madre_id, p.oculta,
                    ' . $this->privacidad->siVisible($this->privacidad->sqlEventoDe('p', 'nacimiento', 'fecha')) . ' AS nacimiento,
                    ' . $this->privacidad->siVisible($this->privacidad->sqlEventoDe('p', 'nacimiento', 'lugar_nombre')) . " AS lugar_nacimiento,
                    " . $this->privacidad->siVisible($this->privacidad->sqlEventoDe('p', 'defuncion', 'fecha')) . " AS defuncion
               FROM coleccion_personas cp JOIN {$this->privacidad->personas()} p ON p.id = cp.persona_id
              WHERE cp.coleccion_id = ?
              ORDER BY p.id",
            [$id]
        ));
    }

    /** @param array<string, mixed> $fila */
    private static function normalizar(array $fila): array
    {
        // SQLite returns 0/1 where PostgreSQL returns a real boolean.
        $fila['oculta'] = (bool)$fila['oculta'];

        return $fila;
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

    /**
     * Private trees (publica = false) are left out unless $incluirPrivadas:
     * a search must not even reveal that they exist.
     *
     * @return list<array>
     */
    public function buscar(string $texto, int $limit = 50, int $offset = 0, bool $incluirPrivadas = false): array
    {
        return $this->db->fetchAll(
            'SELECT id, nombre FROM colecciones WHERE ' . self::sinAcentos('nombre') . self::LIKE_ESCAPED
                . ($incluirPrivadas ? '' : ' AND publica') . ' ORDER BY nombre, id'
                . self::page($limit, $offset),
            [self::patron($texto)]
        );
    }
}
