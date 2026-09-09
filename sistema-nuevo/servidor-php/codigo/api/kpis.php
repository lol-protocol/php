<?php

declare(strict_types=1);

/** Endpoint: /api/kpis. Métricas agregadas para el dashboard inicial. */

function api_kpis(): void
{
    echo json_encode(AlmacenKpis::resumen(), JSON_UNESCAPED_UNICODE);
}
