<?php

declare(strict_types=1);

const API_TIPOS_ALERTA_VALIDOS = ['ip_pais', 'cambio_pais'];

function api_alertas_config(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $almacen = new AlmacenConfiguracion(ConexionBd::obtener());
        echo json_encode([
            'alertas' => [
                'ip_pais' => $almacen->esAlertaHabilitada('ip_pais'),
                'cambio_pais' => $almacen->esAlertaHabilitada('cambio_pais'),
            ],
            'umbral' => $almacen->obtenerUmbral(),
        ]);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!auth_validar_csrf_header()) {
            http_response_code(403);
            echo json_encode(['error' => 'token CSRF inválido']);
            return;
        }

        $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $almacen = new AlmacenConfiguracion(ConexionBd::obtener());

        if (isset($body['alertas'])) {
            foreach ($body['alertas'] as $tipo => $habilitado) {
                if (in_array($tipo, API_TIPOS_ALERTA_VALIDOS, true)) {
                    $almacen->guardar('alerta_' . $tipo, $habilitado ? 'true' : 'false');
                }
            }
        }

        if (isset($body['umbral'])) {
            $umbral = max(0, min(100, (int)$body['umbral']));
            $almacen->guardar('umbral_sensibilidad', (string)$umbral);
        }

        echo json_encode(['ok' => true]);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'método no permitido']);
}
