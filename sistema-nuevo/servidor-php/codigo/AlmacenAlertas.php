<?php

declare(strict_types=1);

require_once __DIR__ . '/ConexionBd.php';

final class AlmacenAlertas
{
    private const LIMITE_USUARIOS = 15;

    public static function ipMismatches(): array
    {
        $pdo = ConexionBd::obtener();
        $where = 'a.ip_pais_codigo IS NOT NULL AND a.ip_pais_codigo <> u.pais_codigo';
        $resumen = $pdo->query("SELECT COUNT(*) AS total, COUNT(DISTINCT a.usuario_id) AS usuarios FROM acciones a JOIN usuarios u ON u.id = a.usuario_id WHERE $where")->fetch();
        $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.pais_codigo, COUNT(*) AS cnt, MAX(a.marca_temporal) AS last_seen FROM acciones a JOIN usuarios u ON u.id = a.usuario_id WHERE $where GROUP BY u.id, u.nombre, u.pais_codigo ORDER BY cnt DESC LIMIT :l");
        $stmt->bindValue(':l', self::LIMITE_USUARIOS, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total_mismatches' => (int) $resumen['total'],
            'total_users_affected' => (int) $resumen['usuarios'],
            'top' => array_map(fn ($f) => [
                'user_id' => $f['id'],
                'user_name' => $f['nombre'],
                'country' => $f['pais_codigo'],
                'mismatch_count' => (int) $f['cnt'],
                'last_seen' => $f['last_seen'],
            ], $stmt->fetchAll()),
        ];
    }

    /** @param int $umbral Sensibilidad 0-100: más alto = ventana de tiempo más amplia cuenta como "cambio imposible". */
    public static function cambiosPaisImposibles(int $umbral = 50): array
    {
        $horasUmbral = 0.5 + (max(0, min(100, $umbral)) / 100) * 3.5; // rango 0.5h (umbral=0) .. 4h (umbral=100)

        $pdo = ConexionBd::obtener();
        $cte = 'WITH c AS (SELECT a.usuario_id, u.nombre, LAG(a.ip_pais_codigo) OVER (PARTITION BY a.usuario_id ORDER BY a.marca_temporal) AS p1, a.ip_pais_codigo AS p2, LAG(a.marca_temporal) OVER (PARTITION BY a.usuario_id ORDER BY a.marca_temporal) AS t1, a.marca_temporal AS t2 FROM acciones a JOIN usuarios u ON u.id = a.usuario_id WHERE a.ip_pais_codigo IS NOT NULL)';
        $condicion = 'p1 IS NOT NULL AND p1 <> p2 AND (EXTRACT(EPOCH FROM (t2 - t1)) / 3600) < :horas';

        $resumen = $pdo->prepare("$cte SELECT COUNT(*) AS total, COUNT(DISTINCT usuario_id) AS usuarios FROM c WHERE $condicion");
        $resumen->bindValue(':horas', $horasUmbral);
        $resumen->execute();
        $resumen = $resumen->fetch();

        $stmt = $pdo->prepare("$cte SELECT usuario_id AS id, nombre, p1, p2, COUNT(*) AS cnt, MAX(t2) AS last_seen FROM c WHERE $condicion GROUP BY usuario_id, nombre, p1, p2 ORDER BY cnt DESC LIMIT :l");
        $stmt->bindValue(':horas', $horasUmbral);
        $stmt->bindValue(':l', self::LIMITE_USUARIOS, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total_changes' => (int) $resumen['total'],
            'total_users_affected' => (int) $resumen['usuarios'],
            'top' => array_map(fn ($f) => [
                'user_id' => $f['id'],
                'user_name' => $f['nombre'],
                'pais_anterior' => $f['p1'],
                'pais_actual' => $f['p2'],
                'cambio_count' => (int) $f['cnt'],
                'last_seen' => $f['last_seen'],
            ], $stmt->fetchAll()),
        ];
    }
}
