<?php

declare(strict_types=1);

/** Endpoint principal: /api/timeline. Paginado y filtrable por tipo de acción. */

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

    $tipoRaw = trim((string) ($_GET['type'] ?? ''));
    $tipo = ($tipoRaw === '' || $tipoRaw === 'all') ? null : $tipoRaw;

    $pagina = api_pagina_desde_query();
    $porPagina = api_por_pagina_desde_query();

    $paginaAcciones = AlmacenAcciones::pagina($userId, $tipo, $pagina, $porPagina);
    $cohortes = api_timeline_con_cohortes(
        $paginaAcciones['items'], $userId, $user['country'], $countries, $ageMin, $ageMax, $gender
    );

    echo json_encode([
        'user' => $user + ['country_name' => $groups['countries'][$user['country']] ?? $user['country']],
        'filters' => [
            'scope' => $scope,
            'scope_label' => api_scope_label($scope, $groups),
            'age_min' => $ageMin,
            'age_max' => $ageMax,
            'gender' => $gender,
            'type' => $tipo ?? 'all',
        ],
        'pagination' => api_pagination_meta($paginaAcciones['total'], $pagina, $porPagina),
        'chart' => AlmacenAcciones::resumenDiario($userId, $tipo),
        'stats_service_available' => $cohortes['stats_service_available'],
        'timeline' => $cohortes['timeline'],
    ], JSON_UNESCAPED_UNICODE);
}
