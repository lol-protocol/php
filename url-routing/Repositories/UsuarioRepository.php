<?php

declare(strict_types=1);

namespace App\Repositories;

/** Both sites have a usuarios table with the same shape. */
final class UsuarioRepository extends Repository
{
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id, email, nombre, creado_en FROM usuarios WHERE id = ?', [$id]);
    }

    /** POS only. @return list<array> */
    public function deseos(int $usuarioId): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.nombre, p.precio_centavos, p.moneda, p.activo
               FROM deseos d JOIN productos p ON p.id = d.producto_id
              WHERE d.usuario_id = ?
              ORDER BY d.agregado_en DESC, p.id',
            [$usuarioId]
        );
    }

    /** POS only. @return list<array> the default address first */
    public function direcciones(int $usuarioId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT alias, destinatario, calle, ciudad, codigo_postal, pais, principal
               FROM direcciones WHERE usuario_id = ?
              ORDER BY principal DESC, alias',
            [$usuarioId]
        );

        return array_map(static fn(array $r) => ['principal' => (bool)$r['principal']] + $r, $rows);
    }
}
