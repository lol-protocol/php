<?php

declare(strict_types=1);

/**
 * Endpoint: /api/alerts. Anomalías proactivas según configuración. Cada tipo habilitado llega bajo el mismo id que en
 * /api/alerts-config (ip_pais, cambio_pais) y con la misma forma (ver AlmacenAlertas::alerta()).
 */

function api_alerts(): void
{
    $pdo = ConexionBd::obtener();
    $almacen = new AlmacenConfiguracion($pdo);
    $alertasStore = new AlmacenAlertas($pdo);
    $alertas = [];

    if ($almacen->esAlertaHabilitada('ip_pais')) {
        $alertas['ip_pais'] = $alertasStore->ipMismatches();
    }

    if ($almacen->esAlertaHabilitada('cambio_pais')) {
        $alertas['cambio_pais'] = $alertasStore->cambiosPaisImposibles($almacen->obtenerUmbral());
    }

    api_responder($alertas);
}
