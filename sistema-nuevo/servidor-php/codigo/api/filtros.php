<?php

declare(strict_types=1);

function api_filtros(): void
{
    $almacen = new AlmacenFiltros(ConexionBd::obtener());

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode($almacen->obtenerTodos(), JSON_UNESCAPED_UNICODE);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        $id = $almacen->crear($nombre, $scope, $ageMin, $ageMax, $gender, $tipoAccion);
        echo json_encode(['id' => $id, 'ok' => true]);
    }
}

function api_filtros_delete(int $id): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['error' => 'método no permitido']);
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
