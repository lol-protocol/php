<?php

declare(strict_types=1);

require_once __DIR__ . '/ConexionBd.php';

/**
 * Detección proactiva de anomalías (no solo el aviso pasivo en cada tarjeta):
 * - IP que no coincide con país declarado
 * - Cambios de país físicamente imposibles (velocidad de viaje > 900 km/h)
 */
final class AlmacenAlertas
{
    private const LIMITE_USUARIOS = 15;
    private const VELOCIDAD_MAX_KMH = 900; // vuelos comerciales máximo
    private const CONDICION_MISMATCH =
        'a.ip_pais_codigo IS NOT NULL AND a.ip_pais_codigo <> u.pais_codigo';

    /** @return array{total_mismatches:int, total_users_affected:int, top: array} */
    public static function ipMismatches(): array
    {
        $pdo = ConexionBd::obtener();

        $resumen = $pdo->query(
            'SELECT COUNT(*) AS total, COUNT(DISTINCT a.usuario_id) AS usuarios
             FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
             WHERE ' . self::CONDICION_MISMATCH
        )->fetch();

        $stmt = $pdo->prepare(
            'SELECT u.id AS user_id, u.nombre AS user_name, u.pais_codigo AS country,
                    COUNT(*) AS mismatch_count, MAX(a.marca_temporal) AS last_seen
             FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
             WHERE ' . self::CONDICION_MISMATCH . '
             GROUP BY u.id, u.nombre, u.pais_codigo
             ORDER BY mismatch_count DESC, u.nombre
             LIMIT :limite'
        );
        $stmt->bindValue('limite', self::LIMITE_USUARIOS, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total_mismatches' => (int) $resumen['total'],
            'total_users_affected' => (int) $resumen['usuarios'],
            'top' => array_map(fn ($f) => [
                'user_id' => $f['user_id'],
                'user_name' => $f['user_name'],
                'country' => $f['country'],
                'mismatch_count' => (int) $f['mismatch_count'],
                'last_seen' => $f['last_seen'],
            ], $stmt->fetchAll()),
        ];
    }

    /** @return array{total_changes:int, total_users_affected:int, top: array} */
    public static function cambiosPaisImposibles(): array
    {
        $pdo = ConexionBd::obtener();

        $resumen = $pdo->query(
            'WITH cambios AS (
                SELECT a.usuario_id,
                       LAG(a.ip_pais_codigo) OVER (PARTITION BY a.usuario_id ORDER BY a.marca_temporal) AS pais_anterior,
                       a.ip_pais_codigo AS pais_actual,
                       LAG(a.marca_temporal) OVER (PARTITION BY a.usuario_id ORDER BY a.marca_temporal) AS tiempo_anterior,
                       a.marca_temporal AS tiempo_actual
                FROM acciones a
                WHERE a.ip_pais_codigo IS NOT NULL
            )
            SELECT COUNT(*) AS total, COUNT(DISTINCT usuario_id) AS usuarios
            FROM cambios
            WHERE pais_anterior IS NOT NULL
              AND pais_anterior <> pais_actual
              AND (EXTRACT(EPOCH FROM (tiempo_actual - tiempo_anterior)) / 3600) < 2'
        )->fetch();

        $stmt = $pdo->prepare(
            'WITH cambios AS (
                SELECT a.usuario_id,
                       u.nombre AS user_name,
                       LAG(a.ip_pais_codigo) OVER (PARTITION BY a.usuario_id ORDER BY a.marca_temporal) AS pais_anterior,
                       a.ip_pais_codigo AS pais_actual,
                       LAG(a.marca_temporal) OVER (PARTITION BY a.usuario_id ORDER BY a.marca_temporal) AS tiempo_anterior,
                       a.marca_temporal AS tiempo_actual
                FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
                WHERE a.ip_pais_codigo IS NOT NULL
            )
            SELECT usuario_id AS user_id, user_name, pais_anterior, pais_actual,
                   COUNT(*) AS cambio_count, MAX(tiempo_actual) AS last_seen
            FROM cambios
            WHERE pais_anterior IS NOT NULL
              AND pais_anterior <> pais_actual
              AND (EXTRACT(EPOCH FROM (tiempo_actual - tiempo_anterior)) / 3600) < 2
            GROUP BY usuario_id, user_name, pais_anterior, pais_actual
            ORDER BY cambio_count DESC, user_name
            LIMIT :limite'
        );
        $stmt->bindValue('limite', self::LIMITE_USUARIOS, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total_changes' => (int) $resumen['total'],
            'total_users_affected' => (int) $resumen['usuarios'],
            'top' => array_map(fn ($f) => [
                'user_id' => $f['user_id'],
                'user_name' => $f['user_name'],
                'pais_anterior' => $f['pais_anterior'],
                'pais_actual' => $f['pais_actual'],
                'cambio_count' => (int) $f['cambio_count'],
                'last_seen' => $f['last_seen'],
            ], $stmt->fetchAll()),
        ];
    }
}
