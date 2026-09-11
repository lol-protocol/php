<?php

declare(strict_types=1);

require_once __DIR__ . '/../AlmacenConfiguracion.php';

function api_alertas_config(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $almacen = new AlmacenConfiguracion(ConexionBd::obtener());
        echo json_encode([
            'alertas' => [
                'ip_pais' => $almacen->esAlertaHabilitada('ip_pais'),
                'cambio_pais' => $almacen->esAlertaHabilitada('cambio_pais'),
                'logins_fallidos' => $almacen->esAlertaHabilitada('logins_fallidos'),
            ],
            'umbral' => $almacen->obtenerUmbral(),
        ]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $almacen = new AlmacenConfiguracion(ConexionBd::obtener());

        if (isset($body['alertas'])) {
            foreach ($body['alertas'] as $tipo => $habilitado) {
                $almacen->guardar('alerta_' . $tipo, $habilitado ? 'true' : 'false');
            }
        }

        if (isset($body['umbral'])) {
            $umbral = max(0, min(100, (int)$body['umbral']));
            $almacen->guardar('umbral_sensibilidad', (string)$umbral);
        }

        echo json_encode(['ok' => true]);
    }
}
