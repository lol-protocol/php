<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth;
use App\Database;
use App\Paginacion;
use LogicException;
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
     *
     * Exige estar dentro de Database::transaccion(): la entrada tiene que
     * quedar en la misma transaccion que el cambio que describe, o un cambio
     * puede quedar sin auditar si la segunda escritura falla. Asi era en ocho
     * flujos (altas, ediciones, usuarios); con la exigencia, un flujo nuevo
     * que se olvide la transaccion falla en los tests en vez de en silencio.
     */
    public static function auditarComoUsuarioActual(string $accion, string $entidad, int $entidadId, string $detalle): void
    {
        if (!Database::connection()->inTransaction()) {
            throw new LogicException(sprintf(
                'La auditoria de "%s %s #%d" tiene que registrarse dentro de Database::transaccion(), junto con el cambio que describe.',
                $accion,
                $entidad,
                $entidadId
            ));
        }

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

    /** @return array{filas: array, total: int, totalPaginas: int, pagina: int} */
    public function listado(int $pagina = 1): array
    {
        $total = (int) $this->db->query('SELECT COUNT(*) FROM auditoria')->fetchColumn();
        $pagina = Paginacion::acotar($pagina, $total);

        $stmt = $this->db->prepare(
            "SELECT a.creado_en, a.accion, a.entidad, a.entidad_id, a.detalle,
                    COALESCE(u.nombre, 'Sistema') AS usuario
             FROM auditoria a
             LEFT JOIN usuarios_sistema u ON u.id = a.usuario_id
             ORDER BY a.creado_en DESC, a.id DESC
             LIMIT :limite OFFSET :offset"
        );
        $stmt->bindValue(':limite', Paginacion::POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', Paginacion::offset($pagina), PDO::PARAM_INT);
        $stmt->execute();

        return [
            'filas' => $stmt->fetchAll(),
            'total' => $total,
            'totalPaginas' => Paginacion::totalPaginas($total),
            'pagina' => $pagina,
        ];
    }
}
