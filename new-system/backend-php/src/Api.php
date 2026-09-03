<?php

declare(strict_types=1);

/**
 * Manejadores de los endpoints /api/*. Cada uno escribe directamente la respuesta JSON.
 */

function api_users(): void
{
    $groups = DataStore::groups();
    $users = array_map(static function (array $u) use ($groups): array {
        $u['country_name'] = $groups['countries'][$u['country']] ?? $u['country'];
        return $u;
    }, DataStore::users());

    usort($users, fn ($a, $b) => $a['name'] <=> $b['name']);

    echo json_encode($users, JSON_UNESCAPED_UNICODE);
}

function api_groups(): void
{
    echo json_encode(DataStore::groups(), JSON_UNESCAPED_UNICODE);
}

function api_timeline(): void
{
    $userId = $_GET['user_id'] ?? '';
    if ($userId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'user_id es requerido']);
        return;
    }

    $user = DataStore::userById($userId);
    if ($user === null) {
        http_response_code(404);
        echo json_encode(['error' => 'usuario no encontrado']);
        return;
    }

    $groups = DataStore::groups();
    $scope = $_GET['scope'] ?? 'all';
    $countries = api_resolve_scope_countries($scope, $groups);

    $ageMin = isset($_GET['age_min']) ? (int) $_GET['age_min'] : 0;
    $ageMax = isset($_GET['age_max']) ? (int) $_GET['age_max'] : 150;
    $gender = $_GET['gender'] ?? 'all';

    $client = new StatsClient();
    $statsCache = [];
    $timeline = [];

    foreach (DataStore::actionsByUser($userId) as $action) {
        $type = $action['type'];
        if (!array_key_exists($type, $statsCache)) {
            $statsCache[$type] = $client->stats($type, $countries, $ageMin, $ageMax, $gender, $userId);
        }
        $cohort = $statsCache[$type];

        $timeline[] = $action + [
            'cohort' => $cohort,
            'duration_delta_pct' => api_delta_pct($action['duration_ms'], $cohort['avg_duration_ms'] ?? null),
            'amount_delta_pct' => $action['amount_usd'] === null
                ? null
                : api_delta_pct($action['amount_usd'], $cohort['avg_amount_usd'] ?? null),
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

function api_not_found(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'ruta no encontrada']);
}

function api_delta_pct(int|float $value, int|float|null $average): ?float
{
    if ($average === null || $average <= 0) {
        return null;
    }
    return (($value - $average) / $average) * 100;
}

/** @return string[]|null null = sin filtro de país (todos los países) */
function api_resolve_scope_countries(string $scope, array $groups): ?array
{
    if ($scope === '' || $scope === 'all') {
        return null;
    }
    if (str_starts_with($scope, 'preset:')) {
        $key = substr($scope, strlen('preset:'));
        foreach ($groups['presets'] as $preset) {
            if ($preset['key'] === $key) {
                return $preset['countries'];
            }
        }
        return null;
    }
    if (str_starts_with($scope, 'country:')) {
        return [substr($scope, strlen('country:'))];
    }
    return null;
}

function api_scope_label(string $scope, array $groups): string
{
    if ($scope === '' || $scope === 'all') {
        return 'Todos los países';
    }
    if (str_starts_with($scope, 'preset:')) {
        $key = substr($scope, strlen('preset:'));
        foreach ($groups['presets'] as $preset) {
            if ($preset['key'] === $key) {
                return $preset['label'];
            }
        }
    }
    if (str_starts_with($scope, 'country:')) {
        $code = substr($scope, strlen('country:'));
        return $groups['countries'][$code] ?? $code;
    }
    return $scope;
}
