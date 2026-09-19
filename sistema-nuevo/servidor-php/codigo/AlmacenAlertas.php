<?php

declare(strict_types=1);

final class AlmacenAlertas
{
    private const LIMITE_USUARIOS = 15;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ipMismatches(): array
    {
        $where = 'a.ip_pais_codigo IS NOT NULL AND a.ip_pais_codigo <> u.pais_codigo';

        $resumen = $this->pdo->query(<<<SQL
            SELECT COUNT(*) AS total, COUNT(DISTINCT a.usuario_id) AS usuarios
            FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
            WHERE $where
            SQL)->fetch();

        $stmt = $this->pdo->prepare(<<<SQL
            SELECT u.id, u.nombre, u.pais_codigo, COUNT(*) AS cantidad, MAX(a.marca_temporal) AS last_seen
            FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
            WHERE $where
            GROUP BY u.id, u.nombre, u.pais_codigo
            ORDER BY cantidad DESC, u.id
            LIMIT :limite
            SQL);
        $stmt->bindValue('limite', self::LIMITE_USUARIOS, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total_mismatches' => (int) $resumen['total'],
            'total_users_affected' => (int) $resumen['usuarios'],
            'top' => array_map(fn ($f) => self::conUsuario($f) + [
                'country' => $f['pais_codigo'], 'mismatch_count' => (int) $f['cantidad'], 'last_seen' => $f['last_seen'],
            ], $stmt->fetchAll()),
        ];
    }

    /** @param int $umbral Sensibilidad 0-100: más alto = ventana de tiempo más amplia cuenta como "cambio imposible". */
    public function cambiosPaisImposibles(int $umbral = 50): array
    {
        $horasUmbral = 0.5 + (max(0, min(100, $umbral)) / 100) * 3.5; // rango 0.5h (umbral=0) .. 4h (umbral=100)

        $cte = <<<SQL
            WITH cambios AS (
                SELECT a.usuario_id, u.nombre, LAG(a.ip_pais_codigo) OVER ventana AS pais_anterior, a.ip_pais_codigo AS pais_actual,
                       LAG(a.marca_temporal) OVER ventana AS tiempo_anterior, a.marca_temporal AS tiempo_actual
                FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
                WHERE a.ip_pais_codigo IS NOT NULL
                WINDOW ventana AS (PARTITION BY a.usuario_id ORDER BY a.marca_temporal)
            )
            SQL;
        $condicion = <<<SQL
            pais_anterior IS NOT NULL AND pais_anterior <> pais_actual AND (EXTRACT(EPOCH FROM (tiempo_actual - tiempo_anterior)) / 3600) < :horas
            SQL;

        $resumenStmt = $this->pdo->prepare("$cte SELECT COUNT(*) AS total, COUNT(DISTINCT usuario_id) AS usuarios FROM cambios WHERE $condicion");
        $resumenStmt->bindValue('horas', $horasUmbral);
        $resumenStmt->execute();
        $resumen = $resumenStmt->fetch();

        // LIMITE_USUARIOS es sobre usuarios distintos, no filas: sin el DISTINCT ON,
        // un usuario con varios pares de país "imposibles" ocuparía varios lugares
        // del LIMIT -- se queda solo con su par más frecuente (más reciente si empata).
        $stmt = $this->pdo->prepare(<<<SQL
            $cte,
            por_usuario AS (
                SELECT DISTINCT ON (usuario_id) usuario_id, nombre, pais_anterior, pais_actual, COUNT(*) AS cantidad, MAX(tiempo_actual) AS last_seen
                FROM cambios
                WHERE $condicion
                GROUP BY usuario_id, nombre, pais_anterior, pais_actual
                ORDER BY usuario_id, COUNT(*) DESC, MAX(tiempo_actual) DESC, pais_actual
            )
            SELECT usuario_id AS id, nombre, pais_anterior, pais_actual, cantidad, last_seen
            FROM por_usuario
            ORDER BY cantidad DESC, usuario_id
            LIMIT :limite
            SQL);
        $stmt->bindValue('horas', $horasUmbral);
        $stmt->bindValue('limite', self::LIMITE_USUARIOS, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total_changes' => (int) $resumen['total'],
            'total_users_affected' => (int) $resumen['usuarios'],
            'top' => array_map(fn ($f) => self::conUsuario($f) + [
                'pais_anterior' => $f['pais_anterior'], 'pais_actual' => $f['pais_actual'], 'cambio_count' => (int) $f['cantidad'], 'last_seen' => $f['last_seen'],
            ], $stmt->fetchAll()),
        ];
    }

    private static function conUsuario(array $fila): array
    {
        return ['user_id' => $fila['id'], 'user_name' => $fila['nombre']];
    }
}
