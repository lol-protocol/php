<?php

declare(strict_types=1);

const API_TIPOS_ALERTA_VALIDOS = ['ip_pais', 'cambio_pais'];

function api_alertas_config(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $almacen = new AlmacenConfiguracion(ConexionBd::obtener());
        api_responder([
            'alertas' => [
                'ip_pais' => $almacen->esAlertaHabilitada('ip_pais'),
                'cambio_pais' => $almacen->esAlertaHabilitada('cambio_pais'),
            ],
            'umbral' => $almacen->obtenerUmbral(),
        ]);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!api_exigir_csrf()) {
            return;
        }

        $body = api_cuerpo_json();
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

        api_responder(['ok' => true]);
        return;
    }

    api_metodo_no_permitido();
}
