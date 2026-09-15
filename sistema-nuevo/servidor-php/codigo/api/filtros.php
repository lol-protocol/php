<?php

declare(strict_types=1);

function api_filtros(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $almacen = new AlmacenFiltros(ConexionBd::obtener());
        echo json_encode($almacen->obtenerTodos(), JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!auth_validar_csrf_header()) {
            http_response_code(403);
            echo json_encode(['error' => 'token CSRF inválido']);
            return;
        }

        $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];

        $nombre = is_string($body['nombre'] ?? null) ? trim($body['nombre']) : '';
        $scope = $body['scope'] ?? 'all_countries';
        $ageMin = isset($body['age_min']) ? (int)$body['age_min'] : null;
        $ageMax = isset($body['age_max']) ? (int)$body['age_max'] : null;
        $gender = $body['gender'] ?? null;
        $tipoAccion = $body['tipo_accion'] ?? null;

        if ($nombre === '') {
            http_response_code(400);
            echo json_encode(['error' => 'falta nombre del filtro']);
            return;
        }

        $almacen = new AlmacenFiltros(ConexionBd::obtener());
        $id = $almacen->crear($nombre, $scope, $ageMin, $ageMax, $gender, $tipoAccion);
        echo json_encode(['id' => $id, 'ok' => true]);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'método no permitido']);
}

function api_filtros_delete(int $id): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['error' => 'método no permitido']);
        return;
    }

    if (!auth_validar_csrf_header()) {
        http_response_code(403);
        echo json_encode(['error' => 'token CSRF inválido']);
        return;
    }

    $almacen = new AlmacenFiltros(ConexionBd::obtener());
    if ($almacen->eliminar($id)) {
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'filtro no encontrado']);
    }
}
