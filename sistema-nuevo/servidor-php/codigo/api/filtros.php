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
            api_error(403, 'csrf_invalido', 'token CSRF inválido');
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
            api_error(400, 'filtro_nombre_requerido', 'falta nombre del filtro');
            return;
        }
        if (mb_strlen($nombre) > 100) {
            api_error(400, 'filtro_nombre_largo', 'el nombre no puede superar los 100 caracteres');
            return;
        }

        $almacen = new AlmacenFiltros(ConexionBd::obtener());
        $id = $almacen->crear($nombre, $scope, $ageMin, $ageMax, $gender, $tipoAccion);
        echo json_encode(['id' => $id, 'ok' => true], JSON_UNESCAPED_UNICODE);
        return;
    }

    api_error(405, 'metodo_no_permitido', 'método no permitido');
}

function api_filtros_delete(int $id): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        api_error(405, 'metodo_no_permitido', 'método no permitido');
        return;
    }

    if (!auth_validar_csrf_header()) {
        api_error(403, 'csrf_invalido', 'token CSRF inválido');
        return;
    }

    $almacen = new AlmacenFiltros(ConexionBd::obtener());
    if ($almacen->eliminar($id)) {
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    } else {
        api_error(404, 'filtro_no_encontrado', 'filtro no encontrado');
    }
}
