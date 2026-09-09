<?php

declare(strict_types=1);

require_once __DIR__ . '/ConexionBd.php';

/** Métricas agregadas para el dashboard inicial, antes de elegir un usuario puntual. */
final class AlmacenKpis
{
    public static function resumen(): array
    {
        $pdo = ConexionBd::obtener();

        $totales = $pdo->query(
            'SELECT (SELECT COUNT(*) FROM usuarios) AS usuarios,
                    COUNT(*) AS acciones,
                    COALESCE(SUM(monto_usd), 0) AS gasto_usd,
                    MAX(marca_temporal) AS ultima_accion
             FROM acciones'
        )->fetch();

        $porTipo = $pdo->query(
            'SELECT a.tipo_clave AS clave, COUNT(*) AS cantidad
             FROM acciones a
             GROUP BY a.tipo_clave ORDER BY cantidad DESC LIMIT 5'
        )->fetchAll();

        $porPais = $pdo->query(
            'SELECT u.pais_codigo AS country, COUNT(*) AS cantidad
             FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
             GROUP BY u.pais_codigo ORDER BY cantidad DESC LIMIT 5'
        )->fetchAll();

        return [
            'total_users' => (int) $totales['usuarios'],
            'total_actions' => (int) $totales['acciones'],
            'total_spend_usd' => round((float) $totales['gasto_usd'], 2),
            'last_action_at' => $totales['ultima_accion'],
            'top_action_types' => array_map(fn ($f) => ['type' => $f['clave'], 'count' => (int) $f['cantidad']], $porTipo),
            'top_countries' => array_map(fn ($f) => ['country' => $f['country'], 'count' => (int) $f['cantidad']], $porPais),
        ];
    }
}
