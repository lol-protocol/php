<?php

declare(strict_types=1);

/** Endpoint: /api/alerts. Anomalías proactivas (por ahora: IPs fuera del país declarado). */

function api_alerts(): void
{
    echo json_encode(AlmacenAlertas::ipMismatches(), JSON_UNESCAPED_UNICODE);
}
