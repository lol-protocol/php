<?php

declare(strict_types=1);

/** Endpoint: /api/alerts. Anomalías proactivas según configuración. */

function api_alerts(): void
{
    $pdo = ConexionBd::obtener();
    $almacen = new AlmacenConfiguracion($pdo);
    $alertasStore = new AlmacenAlertas($pdo);
    $alertas = [];

    if ($almacen->esAlertaHabilitada('ip_pais')) {
        $alertas['ip_pais_mismatch'] = $alertasStore->ipMismatches();
    }

    if ($almacen->esAlertaHabilitada('cambio_pais')) {
        $alertas['cambios_pais_imposibles'] = $alertasStore->cambiosPaisImposibles($almacen->obtenerUmbral());
    }

    echo json_encode($alertas, JSON_UNESCAPED_UNICODE);
}
