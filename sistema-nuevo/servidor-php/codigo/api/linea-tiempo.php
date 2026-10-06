<?php

declare(strict_types=1);

/** Endpoint principal: /api/timeline. Paginado y filtrable por tipo de acción. */

/**
 * El universo de comparación que pide la query del timeline (?scope=&age_min=&age_max=&gender=): los países del scope, la
 * edad y el género, sin el usuario que se está mirando ($excluirUsuario).
 *
 * @param array<string,mixed> $query
 */
function api_universo_desde_query(array $query, array $groups, string $excluirUsuario): Universo
{
    return new Universo(
        api_resolve_scope_countries($query['scope'] ?? 'all', $groups),
        isset($query['age_min']) ? (int) $query['age_min'] : 0,
        isset($query['age_max']) ? (int) $query['age_max'] : 150,
        $query['gender'] ?? 'all',
        $excluirUsuario
    );
}

function api_timeline(): void
{
    $userId = $_GET['user_id'] ?? '';
    if ($userId === '') {
        api_error(400, 'user_id_requerido', 'user_id es requerido');
        return;
    }

    $pdo = ConexionBd::obtener();
    $datos = new AlmacenDatos($pdo);
    $acciones = new AlmacenAcciones($pdo);

    $user = $datos->userById($userId);
    if ($user === null) {
        api_error(404, 'usuario_no_encontrado', 'usuario no encontrado');
        return;
    }

    $groups = $datos->groups();
    $scope = $_GET['scope'] ?? 'all';
    $universo = api_universo_desde_query($_GET, $groups, $userId);

    $tipoRaw = trim((string) ($_GET['type'] ?? ''));
    $tipo = ($tipoRaw === '' || $tipoRaw === 'all') ? null : $tipoRaw;

    $pagina = api_pagina_desde_query();
    $porPagina = api_por_pagina_desde_query();

    $paginaAcciones = $acciones->pagina($userId, $tipo, $pagina, $porPagina);
    $cohortes = api_timeline_con_cohortes($paginaAcciones['items'], $user['country'], $universo);

    api_responder([
        'user' => $user + ['country_name' => $groups['countries'][$user['country']] ?? $user['country']],
        'filters' => [
            'scope' => $scope,
            'scope_label' => api_scope_label($scope, $groups),
            'age_min' => $universo->edadMin,
            'age_max' => $universo->edadMax,
            'gender' => $universo->genero,
            'type' => $tipo ?? 'all',
        ],
        'pagination' => api_pagination_meta($paginaAcciones['total'], $pagina, $porPagina),
        'chart' => $acciones->resumenDiario($userId, $tipo),
        'stats_service_available' => $cohortes['stats_service_available'],
        'timeline' => $cohortes['timeline'],
    ]);
}
