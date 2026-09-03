<?php

declare(strict_types=1);

require_once __DIR__ . '/ConexionBd.php';

/**
 * Detección proactiva de anomalías (no solo el aviso pasivo en cada tarjeta):
 * usuarios cuyas acciones tienen una IP que no coincide con su país declarado.
 */
final class AlmacenAlertas
{
    private const LIMITE_USUARIOS = 15;
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
}
