<?php

declare(strict_types=1);

/** Endpoints de catálogo: /api/users (paginado, buscable), /api/groups, /api/action-types. */

const API_POR_PAGINA_DEFECTO = 20;
const API_POR_PAGINA_MAXIMO = 100;

function api_pagina_desde_query(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

function api_por_pagina_desde_query(): int
{
    $valor = (int) ($_GET['per_page'] ?? API_POR_PAGINA_DEFECTO);
    return min(API_POR_PAGINA_MAXIMO, max(1, $valor));
}

function api_users(): void
{
    $pagina = api_pagina_desde_query();
    $porPagina = api_por_pagina_desde_query();
    $busqueda = trim((string) ($_GET['search'] ?? ''));

    $resultado = AlmacenDatos::usersPage($pagina, $porPagina, $busqueda);

    echo json_encode([
        'items' => $resultado['items'],
        'pagination' => api_pagination_meta($resultado['total'], $pagina, $porPagina),
    ], JSON_UNESCAPED_UNICODE);
}

function api_groups(): void
{
    echo json_encode(AlmacenDatos::groups(), JSON_UNESCAPED_UNICODE);
}

function api_action_types(): void
{
    echo json_encode(AlmacenAcciones::tipos(), JSON_UNESCAPED_UNICODE);
}
