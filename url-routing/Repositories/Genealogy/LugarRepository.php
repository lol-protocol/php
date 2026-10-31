<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;
use App\Support\Privacidad;

/**
 * Places are addressed by their code path ("mx/jal/gdl"). Queries about a
 * place include everything below it: a country's personas include those
 * born in any of its regions and cities.
 */
final class LugarRepository extends Repository
{
    /** @param list<string> $codes */
    public static function ruta(array $codes): string
    {
        return implode('/', array_map('strtolower', $codes));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $ruta): ?array
    {
        return $this->db->fetchOne(
            'SELECT ruta, padre_ruta, codigo, nombre, nivel FROM lugares WHERE ruta = ?',
            [$ruta]
        );
    }

    /** @return list<array> from the country down to the place itself */
    public function jerarquia(string $ruta): array
    {
        $prefijos = [];
        $partes = explode('/', $ruta);
        for ($i = 1, $n = count($partes); $i <= $n; $i++) {
            $prefijos[] = implode('/', array_slice($partes, 0, $i));
        }

        $marcas = implode(', ', array_fill(0, count($prefijos), '?'));
        return $this->db->fetchAll(
            "SELECT ruta, nombre, nivel FROM lugares WHERE ruta IN ({$marcas}) ORDER BY nivel",
            $prefijos
        );
    }

    /** @return list<array> */
    public function hijos(string $ruta): array
    {
        return $this->db->fetchAll(
            'SELECT ruta, nombre FROM lugares WHERE padre_ruta = ? ORDER BY nombre',
            [$ruta]
        );
    }

    /**
     * Personas born in this place or anywhere below it. Living people are left
     * out: a hidden row would still say that someone was born here, and when. So
     * are births with a living participant (see Privacidad::sucesos()): the place
     * of one is what points at that person.
     *
     * @return list<array>
     */
    public function personas(string $ruta, int $limit = 200): array
    {
        // The page is picked on the births, in the order of sucesos_fecha_idx (fecha, id), and sucesos() asks
        // whether each may be seen only for the ones it reaches, until the page is full; only then are those
        // personas masked, which is what the outer query reads. Joining personas() to every birth in the place
        // first computed `oculta` for all of them before it could sort and cut, so a country's page cost as much
        // as its catalog. The ties of a date at the cut are broken by the event's id, what the index orders by;
        // the rows shown are ordered by date and persona id, as they always were. A sucesos() birth is one with
        // no hidden participant, her own included, so the `NOT p.oculta` of the outer query leaves nobody out
        // today: it stays as the check that cannot be forgotten.
        return $this->db->fetchAll(
            "SELECT DISTINCT p.id, p.nombres, p.apellidos, c.nacimiento, c.lugar_nombre
               FROM (SELECT DISTINCT sp.persona_id AS id, s.fecha AS nacimiento, l.nombre AS lugar_nombre, s.id AS suceso_id
                       FROM {$this->privacidad->sucesos()} s
                       JOIN suceso_participantes sp ON sp.suceso_id = s.id AND sp.rol = '" . Privacidad::ROL_PRINCIPAL . "'
                       JOIN lugares l ON l.ruta = s.lugar_ruta
                      WHERE s.tipo = 'nacimiento' AND (s.lugar_ruta = ? OR s.lugar_ruta LIKE ?)
                      ORDER BY s.fecha, s.id" . self::page($limit) . ") c
               JOIN {$this->privacidad->personas()} p ON p.id = c.id
              WHERE NOT p.oculta
              ORDER BY c.nacimiento, p.id",
            [$ruta, $ruta . '/%']
        );
    }

    /**
     * Events that happened in this place or anywhere below it, except those
     * with a living participant (see Privacidad::sucesos()).
     *
     * @return list<array>
     */
    public function sucesos(string $ruta, int $limit = 200): array
    {
        return $this->db->fetchAll(
            "SELECT s.id, s.tipo, s.fecha, s.descripcion, l.nombre AS lugar_nombre
               FROM {$this->privacidad->sucesos()} s JOIN lugares l ON l.ruta = s.lugar_ruta
              WHERE s.lugar_ruta = ? OR s.lugar_ruta LIKE ?
              ORDER BY s.fecha, s.id" . self::page($limit),
            [$ruta, $ruta . '/%']
        );
    }
}
