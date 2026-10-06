<?php

declare(strict_types=1);

/** Métricas agregadas para el dashboard inicial, antes de elegir un usuario puntual. */
final class AlmacenKpis
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function resumen(): array
    {
        $totales = $this->pdo->query(
            'SELECT (SELECT COUNT(*) FROM usuarios) AS usuarios,
                    COUNT(*) AS acciones,
                    COALESCE(SUM(monto_usd), 0) AS gasto_usd,
                    MAX(marca_temporal) AS ultima_accion
             FROM acciones'
        )->fetch();

        $porTipo = $this->pdo->query(
            'SELECT a.tipo_clave AS clave, COUNT(*) AS cantidad
             FROM acciones a
             GROUP BY a.tipo_clave ORDER BY cantidad DESC, a.tipo_clave LIMIT 5'
        )->fetchAll();

        $porPais = $this->pdo->query(
            'SELECT u.pais_codigo AS country, COUNT(*) AS cantidad
             FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
             GROUP BY u.pais_codigo ORDER BY cantidad DESC, u.pais_codigo LIMIT 5'
        )->fetchAll();

        return [
            'total_users' => (int) $totales['usuarios'],
            'total_actions' => (int) $totales['acciones'],
            'total_spend_usd' => round((float) $totales['gasto_usd'], 2),
            'last_action_at' => $totales['ultima_accion'],
            'active_alerts_users' => $this->usuariosConAlertaActiva(),
            'top_action_types' => array_map(fn ($f) => ['type' => $f['clave'], 'count' => (int) $f['cantidad']], $porTipo),
            'top_countries' => array_map(fn ($f) => ['country' => $f['country'], 'count' => (int) $f['cantidad']], $porPais),
        ];
    }

    /**
     * Usuarios afectados por cualquier tipo de alerta HABILITADO (respeta
     * configuracion_alertas). Suma el conteo de cada tipo -- no deduplica
     * entre tipos (un usuario con ambas anomalías cuenta dos veces).
     * Aceptable para un KPI de pantalla, no para un total exacto.
     *
     * Las reglas viven en AlmacenAlertas, el mismo código que arma el panel de
     * alertas; acá solo se cuentan, sin armar el 'top' (JOIN de nombre + GROUP BY +
     * ORDER BY + LIMIT) que un KPI no necesita.
     */
    private function usuariosConAlertaActiva(): int
    {
        $config = new AlmacenConfiguracion($this->pdo);
        $alertas = new AlmacenAlertas($this->pdo);
        $total = 0;

        if ($config->esAlertaHabilitada('ip_pais')) {
            $total += $alertas->usuariosConIpFueraDelPais();
        }

        if ($config->esAlertaHabilitada('cambio_pais')) {
            $total += $alertas->usuariosConCambioPaisImposible($config->obtenerUmbral());
        }

        return $total;
    }
}
