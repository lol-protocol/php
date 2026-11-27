<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;
use PDOException;

/**
 * Anonimizar a un cliente (migracion 022): se sobrescriben su nombre, email y ciudad, y el nombre y el email de su usuario del funnel;
 * quedan sus boletas, pagos y notas, asi que las cifras no cambian. Los originales se guardan aparte PLAZO_DIAS dias y en ese plazo se
 * puede deshacer; despues purgarVencidos() (bin/purgar_anonimizados.php) los borra y ya no hay vuelta atras.
 *
 * Todo lo que escribe va dentro de una transaccion (la de quien llama, o la propia con Database::transaccion()).
 */
final class AnonimizacionRepository
{
    /** Cuantos dias se puede deshacer. */
    public const PLAZO_DIAS = 7;

    /** Lo que dice el email de un cliente anonimizado: unico por id, y con un dominio que no puede existir (RFC 2606). */
    public static function emailAnonimo(int $clienteId): string
    {
        return "anonimizado-{$clienteId}@anonimizado.invalid";
    }

    public static function nombreAnonimo(int $clienteId): string
    {
        return "Cliente anonimizado #{$clienteId}";
    }

    public const CIUDAD_ANONIMA = '(anonimizada)';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Anonimiza al cliente. false si no existe o ya estaba anonimizado (anonimizar dos veces no pisa los originales guardados).
     * El estado lo decide la propia sentencia con la fila bloqueada, no una lectura de antes: dos pedidos a la vez anonimizan una sola vez.
     */
    public function anonimizar(int $id): bool
    {
        return Database::transaccion(function () use ($id): bool {
            $fila = $this->db->prepare('SELECT nombre, email, ciudad FROM clientes WHERE id = :id AND anonimizado_en IS NULL FOR UPDATE');
            $fila->execute([':id' => $id]);
            $original = $fila->fetch();
            if ($original === false) {
                return false;
            }

            $auditoria = $this->db->prepare(
                "SELECT id, detalle FROM auditoria WHERE entidad = 'cliente' AND entidad_id = :id AND accion = 'crear' ORDER BY id LIMIT 1"
            );
            $auditoria->execute([':id' => $id]);
            $alta = $auditoria->fetch();

            $this->db->prepare(
                "INSERT INTO datos_anonimizados (cliente_id, nombre, email, ciudad, auditoria_id, auditoria_detalle, vence_en)
                 VALUES (:id, :nombre, :email, :ciudad, :aud_id, :aud_detalle, now() + make_interval(days => :dias))"
            )->execute([
                ':id' => $id, ':nombre' => $original['nombre'], ':email' => $original['email'], ':ciudad' => $original['ciudad'],
                ':aud_id' => $alta === false ? null : $alta['id'], ':aud_detalle' => $alta === false ? null : $alta['detalle'],
                ':dias' => self::PLAZO_DIAS,
            ]);

            $this->db->prepare(
                'INSERT INTO datos_anonimizados_funnel (usuario_id, cliente_id, nombre, email)
                 SELECT id, cliente_id, nombre, email FROM usuarios_funnel WHERE cliente_id = :id'
            )->execute([':id' => $id]);
            $this->db->prepare(
                "UPDATE usuarios_funnel SET nombre = 'Usuario anonimizado #' || id,
                                            email = 'anonimizado-funnel-' || id || '@anonimizado.invalid', anonimizado_en = now()
                 WHERE cliente_id = :id"
            )->execute([':id' => $id]);

            if ($alta !== false) {
                $this->db->prepare('UPDATE auditoria SET detalle = :detalle WHERE id = :aud')->execute([
                    ':detalle' => "Cliente #{$id} (datos personales anonimizados)", ':aud' => $alta['id'],
                ]);
            }

            $this->db->prepare(
                'UPDATE clientes SET nombre = :nombre, email = :email, ciudad = :ciudad, anonimizado_en = now() WHERE id = :id'
            )->execute([':nombre' => self::nombreAnonimo($id), ':email' => self::emailAnonimo($id), ':ciudad' => self::CIUDAD_ANONIMA, ':id' => $id]);

            return true;
        });
    }

    /**
     * Cuando vence el plazo para deshacer la anonimizacion del cliente: null si no esta anonimizado, o si el plazo ya paso (los originales
     * se purgaron, o estan por purgarse y ya no se devuelven).
     */
    public function deshacibleHasta(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT vence_en FROM datos_anonimizados WHERE cliente_id = :id AND vence_en > now()');
        $stmt->execute([':id' => $id]);
        $vence = $stmt->fetchColumn();

        return $vence === false ? null : (string) $vence;
    }

    /**
     * Deshace la anonimizacion: devuelve nombre, email y ciudad (y los del usuario del funnel y la entrada de auditoria) y borra lo guardado.
     * false si no hay nada que deshacer o el plazo vencio. Si el email original lo tomo otro cliente mientras tanto, lanza PDOException
     * (unique) y no cambia nada.
     *
     * @throws PDOException
     */
    public function deshacer(int $id): bool
    {
        return Database::transaccion(function () use ($id): bool {
            $stmt = $this->db->prepare('SELECT * FROM datos_anonimizados WHERE cliente_id = :id AND vence_en > now() FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $original = $stmt->fetch();
            if ($original === false) {
                return false;
            }

            $this->db->prepare(
                'UPDATE clientes SET nombre = :nombre, email = :email, ciudad = :ciudad, anonimizado_en = NULL WHERE id = :id'
            )->execute([':nombre' => $original['nombre'], ':email' => $original['email'], ':ciudad' => $original['ciudad'], ':id' => $id]);

            $this->db->prepare(
                'UPDATE usuarios_funnel u SET nombre = d.nombre, email = d.email, anonimizado_en = NULL
                 FROM datos_anonimizados_funnel d WHERE d.usuario_id = u.id AND d.cliente_id = :id'
            )->execute([':id' => $id]);

            if ($original['auditoria_id'] !== null) {
                $this->db->prepare('UPDATE auditoria SET detalle = :detalle WHERE id = :aud')->execute([
                    ':detalle' => $original['auditoria_detalle'], ':aud' => $original['auditoria_id'],
                ]);
            }

            // ON DELETE CASCADE se lleva datos_anonimizados_funnel.
            $this->db->prepare('DELETE FROM datos_anonimizados WHERE cliente_id = :id')->execute([':id' => $id]);

            return true;
        });
    }

    /**
     * Borra los originales cuyo plazo vencio: desde ahi la anonimizacion es definitiva. Devuelve cuantos clientes. Es lo que corre
     * bin/purgar_anonimizados.php (cron, una vez al dia).
     */
    public function purgarVencidos(): int
    {
        return Database::transaccion(function (): int {
            $stmt = $this->db->prepare('DELETE FROM datos_anonimizados WHERE vence_en <= now()');
            $stmt->execute();

            return $stmt->rowCount();
        });
    }

    /** Cuantos clientes tienen sus originales guardados todavia (vencidos o no): lo que bin/estado.php o un reporte pueden mostrar. */
    public function pendientesDePurga(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM datos_anonimizados WHERE vence_en <= now()')->fetchColumn();
    }
}
