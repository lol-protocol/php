<?php

declare(strict_types=1);

/** Endpoint: /api/kpis. Métricas agregadas para el dashboard inicial. */

function api_kpis(): void
{
    $almacen = new AlmacenKpis(ConexionBd::obtener());
    api_responder($almacen->resumen());
}
