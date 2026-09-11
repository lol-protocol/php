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
            'active_alerts_users' => self::usuariosConAlertaActiva($pdo),
            'top_action_types' => array_map(fn ($f) => ['type' => $f['clave'], 'count' => (int) $f['cantidad']], $porTipo),
            'top_countries' => array_map(fn ($f) => ['country' => $f['country'], 'count' => (int) $f['cantidad']], $porPais),
        ];
    }

    /**
     * Usuarios afectados por cualquier tipo de alerta HABILITADO (respeta
     * configuracion_alertas). Suma total_users_affected de cada tipo -- no
     * deduplica entre tipos (un usuario con ambas anomalías cuenta dos veces),
     * a cambio de no truncar al top 15 de cada consulta como haría contar
     * desde 'top'. Aceptable para un KPI de pantalla, no para un total exacto.
     */
    private static function usuariosConAlertaActiva(PDO $pdo): int
    {
        $config = new AlmacenConfiguracion($pdo);
        $total = 0;

        if ($config->esAlertaHabilitada('ip_pais')) {
            $total += AlmacenAlertas::ipMismatches()['total_users_affected'];
        }
        if ($config->esAlertaHabilitada('cambio_pais')) {
            $total += AlmacenAlertas::cambiosPaisImposibles($config->obtenerUmbral())['total_users_affected'];
        }

        return $total;
    }
}
