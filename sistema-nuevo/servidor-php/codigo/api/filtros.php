<?php

declare(strict_types=1);

function api_filtros(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $almacen = new AlmacenFiltros(ConexionBd::obtener());
        api_responder($almacen->obtenerTodos());
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!api_exigir_csrf()) {
            return;
        }

        $body = api_cuerpo_json();

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
        api_responder(['id' => $id, 'ok' => true]);
        return;
    }

    api_metodo_no_permitido();
}

function api_filtros_delete(int $id): void
{
    if (!api_exigir_metodo('DELETE') || !api_exigir_csrf()) {
        return;
    }

    $almacen = new AlmacenFiltros(ConexionBd::obtener());
    if ($almacen->eliminar($id)) {
        api_responder(['ok' => true]);
    } else {
        api_error(404, 'filtro_no_encontrado', 'filtro no encontrado');
    }
}
