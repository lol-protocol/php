<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class AuditoriaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function registrar(?int $usuarioId, string $accion, string $entidad, int $entidadId, string $detalle): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, detalle)
             VALUES (:usuario_id, :accion, :entidad, :entidad_id, :detalle)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':accion' => $accion,
            ':entidad' => $entidad,
            ':entidad_id' => $entidadId,
            ':detalle' => $detalle,
        ]);
    }

    public function listado(int $limite = 200): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.creado_en, a.accion, a.entidad, a.entidad_id, a.detalle,
                    COALESCE(u.nombre, 'Sistema') AS usuario
             FROM auditoria a
             LEFT JOIN usuarios_sistema u ON u.id = a.usuario_id
             ORDER BY a.creado_en DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
