<?php

declare(strict_types=1);

/** Le agrega a cada acción de una página su "guía": comparación contra el universo elegido. */

function api_timeline_con_cohortes(
    array $acciones,
    string $userId,
    string $userCountry,
    ?array $countries,
    int $ageMin,
    int $ageMax,
    string $gender
): array {
    $client = new ClienteEstadisticas();
    $statsCache = [];
    $timeline = [];

    foreach ($acciones as $action) {
        $type = $action['type'];
        if (!array_key_exists($type, $statsCache)) {
            $statsCache[$type] = $client->stats($type, $countries, $ageMin, $ageMax, $gender, $userId);
        }
        $cohort = $statsCache[$type];

        // amount_local/currency/comment/endpoint/http_status/file_size_kb/path/ip*
        // ya vienen en $action (esquema canónico saneado); se pasan tal cual.
        $timeline[] = $action + [
            'cohort' => $cohort,
            'duration_delta_pct' => api_delta_pct($action['duration_ms'], $cohort['avg_duration_ms'] ?? null),
            'amount_delta_pct' => $action['amount_usd'] === null
                ? null
                : api_delta_pct($action['amount_usd'], $cohort['avg_amount_usd'] ?? null),
            // La IP no siempre coincide con el país declarado del usuario (VPN/proxy/viaje).
            'ip_mismatch' => $action['ip_country'] !== null && $action['ip_country'] !== $userCountry,
        ];
    }

    return [
        'timeline' => $timeline,
        'stats_service_available' => $statsCache === [] || !in_array(null, $statsCache, true),
    ];
}
