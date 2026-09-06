<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth;
use App\Database;
use PDO;

final class AuditoriaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Registra la accion atribuida al usuario de la sesion actual (o null si
     * no hay sesion). Punto unico usado por los controllers para no repetir
     * "Auth::usuarioActual() + registrar()" en cada uno.
     */
    public static function auditarComoUsuarioActual(string $accion, string $entidad, int $entidadId, string $detalle): void
    {
        $usuario = Auth::usuarioActual();
        (new self())->registrar($usuario['id'] ?? null, $accion, $entidad, $entidadId, $detalle);
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
