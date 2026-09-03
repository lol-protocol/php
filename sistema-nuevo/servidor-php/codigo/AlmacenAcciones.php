<?php

declare(strict_types=1);

require_once __DIR__ . '/ConexionBd.php';

/** Acceso de solo lectura a las acciones de un usuario: paginado, filtrable por tipo. */
final class AlmacenAcciones
{
    private const CAMPOS =
        "a.id, a.usuario_id AS user_id, a.tipo_clave AS type, t.etiqueta AS label,
         a.marca_temporal AS timestamp, a.duracion_ms AS duration_ms, a.ruta AS path,
         a.monto_local AS amount_local, a.moneda_codigo AS currency, a.monto_usd AS amount_usd,
         a.comentario AS comment, a.endpoint AS endpoint, a.codigo_http AS http_status,
         a.tamano_archivo_kb AS file_size_kb, a.ip AS ip, a.ip_pais_codigo AS ip_country,
         a.ip_hora_local AS ip_local_time, a.ip_proveedor AS ip_isp";

    /** @return array{key:string,label:string}[] Catálogo completo de tipos de acción (filtro del timeline). */
    public static function tipos(): array
    {
        $filas = ConexionBd::obtener()->query('SELECT clave, etiqueta FROM tipos_accion ORDER BY etiqueta')->fetchAll();
        return array_map(fn ($f) => ['key' => $f['clave'], 'label' => $f['etiqueta']], $filas);
    }

    /** @return array{items: array, total: int} */
    public static function pagina(string $userId, ?string $tipo, int $pagina, int $porPagina): array
    {
        $pdo = ConexionBd::obtener();
        $condicionTipo = $tipo !== null ? 'AND a.tipo_clave = :tipo' : '';

        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM acciones a WHERE a.usuario_id = :id $condicionTipo");
        self::vincularComunes($stmtTotal, $userId, $tipo);
        $stmtTotal->execute();

        $stmt = $pdo->prepare(
            'SELECT ' . self::CAMPOS . "
             FROM acciones a JOIN tipos_accion t ON t.clave = a.tipo_clave
             WHERE a.usuario_id = :id $condicionTipo
             ORDER BY a.marca_temporal
             LIMIT :limite OFFSET :offset"
        );
        self::vincularComunes($stmt, $userId, $tipo);
        $stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => array_map(self::castNumeros(...), $stmt->fetchAll()), 'total' => (int) $stmtTotal->fetchColumn()];
    }

    /** Acciones por día + gasto acumulado en USD, para el gráfico de evolución. */
    public static function resumenDiario(string $userId, ?string $tipo): array
    {
        $pdo = ConexionBd::obtener();
        $condicionTipo = $tipo !== null ? 'AND tipo_clave = :tipo' : '';
        $stmt = $pdo->prepare(
            "SELECT marca_temporal::date AS dia, COUNT(*) AS cantidad, COALESCE(SUM(monto_usd), 0) AS gasto
             FROM acciones WHERE usuario_id = :id $condicionTipo
             GROUP BY dia ORDER BY dia"
        );
        self::vincularComunes($stmt, $userId, $tipo);
        $stmt->execute();

        $acumulado = 0.0;
        $dias = [];
        foreach ($stmt->fetchAll() as $fila) {
            $acumulado += (float) $fila['gasto'];
            $dias[] = [
                'date' => $fila['dia'],
                'count' => (int) $fila['cantidad'],
                'cumulative_spend_usd' => round($acumulado, 2),
            ];
        }
        return $dias;
    }

    private static function vincularComunes(PDOStatement $stmt, string $userId, ?string $tipo): void
    {
        $stmt->bindValue('id', $userId);
        if ($tipo !== null) {
            $stmt->bindValue('tipo', $tipo);
        }
    }

    private static function castNumeros(array $accion): array
    {
        foreach (['amount_local', 'amount_usd', 'file_size_kb'] as $campo) {
            $accion[$campo] = $accion[$campo] === null ? null : (float) $accion[$campo];
        }
        return $accion;
    }
}
