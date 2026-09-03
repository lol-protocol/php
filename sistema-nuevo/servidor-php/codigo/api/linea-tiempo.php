<?php

declare(strict_types=1);

/** Endpoint principal: /api/timeline. */

function api_timeline(): void
{
    $userId = $_GET['user_id'] ?? '';
    if ($userId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'user_id es requerido']);
        return;
    }

    $user = AlmacenDatos::userById($userId);
    if ($user === null) {
        http_response_code(404);
        echo json_encode(['error' => 'usuario no encontrado']);
        return;
    }

    $groups = AlmacenDatos::groups();
    $scope = $_GET['scope'] ?? 'all';
    $countries = api_resolve_scope_countries($scope, $groups);

    $ageMin = isset($_GET['age_min']) ? (int) $_GET['age_min'] : 0;
    $ageMax = isset($_GET['age_max']) ? (int) $_GET['age_max'] : 150;
    $gender = $_GET['gender'] ?? 'all';

    $client = new ClienteEstadisticas();
    $statsCache = [];
    $timeline = [];

    foreach (AlmacenDatos::actionsByUser($userId) as $action) {
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
            'ip_mismatch' => $action['ip_country'] !== null && $action['ip_country'] !== $user['country'],
        ];
    }

    echo json_encode([
        'user' => $user + ['country_name' => $groups['countries'][$user['country']] ?? $user['country']],
        'filters' => [
            'scope' => $scope,
            'scope_label' => api_scope_label($scope, $groups),
            'age_min' => $ageMin,
            'age_max' => $ageMax,
            'gender' => $gender,
        ],
        'stats_service_available' => $statsCache === [] || !in_array(null, $statsCache, true),
        'timeline' => $timeline,
    ], JSON_UNESCAPED_UNICODE);
}
