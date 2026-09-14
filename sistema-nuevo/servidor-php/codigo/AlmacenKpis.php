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
             GROUP BY a.tipo_clave ORDER BY cantidad DESC LIMIT 5'
        )->fetchAll();

        $porPais = $this->pdo->query(
            'SELECT u.pais_codigo AS country, COUNT(*) AS cantidad
             FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
             GROUP BY u.pais_codigo ORDER BY cantidad DESC LIMIT 5'
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
     * OJO: cuenta directo con SELECT COUNT(DISTINCT ...), sin pasar por
     * AlmacenAlertas::ipMismatches()/cambiosPaisImposibles() -- esos también
     * arman el 'top' (JOIN de nombre + GROUP BY + ORDER BY + LIMIT) que acá
     * no hace falta, así que reusarlos pagaría un JOIN y un ORDER BY de más
     * en cada carga del dashboard solo para tirar el resultado.
     */
    private function usuariosConAlertaActiva(): int
    {
        $config = new AlmacenConfiguracion($this->pdo);
        $total = 0;

        if ($config->esAlertaHabilitada('ip_pais')) {
            $total += (int) $this->pdo->query(<<<SQL
                SELECT COUNT(DISTINCT a.usuario_id)
                FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
                WHERE a.ip_pais_codigo IS NOT NULL AND a.ip_pais_codigo <> u.pais_codigo
                SQL)->fetchColumn();
        }

        if ($config->esAlertaHabilitada('cambio_pais')) {
            $total += $this->contarUsuariosConCambioPais($config->obtenerUmbral());
        }

        return $total;
    }

    /** Misma fórmula de horasUmbral que AlmacenAlertas::cambiosPaisImposibles() -- si cambia una, cambia la otra. */
    private function contarUsuariosConCambioPais(int $umbral): int
    {
        $horasUmbral = 0.5 + (max(0, min(100, $umbral)) / 100) * 3.5;

        $stmt = $this->pdo->prepare(<<<SQL
            WITH cambios AS (
                SELECT usuario_id,
                       LAG(ip_pais_codigo) OVER ventana AS pais_anterior, ip_pais_codigo AS pais_actual,
                       LAG(marca_temporal) OVER ventana AS tiempo_anterior, marca_temporal AS tiempo_actual
                FROM acciones
                WHERE ip_pais_codigo IS NOT NULL
                WINDOW ventana AS (PARTITION BY usuario_id ORDER BY marca_temporal)
            )
            SELECT COUNT(DISTINCT usuario_id) FROM cambios
            WHERE pais_anterior IS NOT NULL AND pais_anterior <> pais_actual
            AND (EXTRACT(EPOCH FROM (tiempo_actual - tiempo_anterior)) / 3600) < :horas
            SQL);
        $stmt->bindValue('horas', $horasUmbral);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
