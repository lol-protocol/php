<?php

declare(strict_types=1);

/** Endpoint: /api/alerts. Anomalías proactivas según configuración. */

function api_alerts(): void
{
    $almacen = new AlmacenConfiguracion(ConexionBd::obtener());
    $alertas = [];

    if ($almacen->esAlertaHabilitada('ip_pais')) {
        $alertas['ip_pais_mismatch'] = AlmacenAlertas::ipMismatches();
    }

    if ($almacen->esAlertaHabilitada('cambio_pais')) {
        $alertas['cambios_pais_imposibles'] = AlmacenAlertas::cambiosPaisImposibles();
    }

    echo json_encode($alertas, JSON_UNESCAPED_UNICODE);
}
