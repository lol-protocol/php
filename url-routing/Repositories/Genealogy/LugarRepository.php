<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

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

    /** @return list<array> personas born in this place or anywhere below it */
    public function personas(string $ruta, int $limit = 200): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT p.id, p.nombres, p.apellidos, s.fecha AS nacimiento, l.nombre AS lugar_nombre
               FROM sucesos s
               JOIN suceso_participantes sp ON sp.suceso_id = s.id
               JOIN personas p ON p.id = sp.persona_id
               JOIN lugares l ON l.ruta = s.lugar_ruta
              WHERE s.tipo = 'nacimiento' AND (s.lugar_ruta = ? OR s.lugar_ruta LIKE ?)
              ORDER BY nacimiento, p.id" . self::page($limit),
            [$ruta, $ruta . '/%']
        );
    }

    /** @return list<array> events that happened in this place or anywhere below it */
    public function sucesos(string $ruta, int $limit = 200): array
    {
        return $this->db->fetchAll(
            'SELECT s.id, s.tipo, s.fecha, s.descripcion, l.nombre AS lugar_nombre
               FROM sucesos s JOIN lugares l ON l.ruta = s.lugar_ruta
              WHERE s.lugar_ruta = ? OR s.lugar_ruta LIKE ?
              ORDER BY s.fecha, s.id' . self::page($limit),
            [$ruta, $ruta . '/%']
        );
    }
}
