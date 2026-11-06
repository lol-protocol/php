<?php

declare(strict_types=1);

/**
 * Valida el cuerpo de POST /api/filtros. Devuelve los valores listos para guardar, o el primer error como
 * [código, mensaje]: un tipo equivocado (un arreglo donde va un texto) o un texto más largo que la columna
 * daba un 500 (TypeError, o "value too long" de PostgreSQL) en vez de un 400 con su código.
 *
 * @param array<string,mixed> $body
 * @return array{fallo: array{string,string}}|array{valores: array{scope:string,age_min:?int,age_max:?int,gender:?string,tipo_accion:?string}}
 */
function validar_filtro_guardado(array $body): array
{
    $scope = $body['scope'] ?? 'all_countries';
    if (!is_string($scope) || $scope === '' || mb_strlen($scope) > 50) {
        return ['fallo' => ['filtro_scope_invalido', 'el alcance debe ser un texto de hasta 50 caracteres']];
    }
    $edades = [];
    foreach (['age_min', 'age_max'] as $campo) {
        $valor = $body[$campo] ?? null;
        if ($valor === null || $valor === '') {
            $edades[$campo] = null;
        } elseif ((is_int($valor) || (is_string($valor) && preg_match('/^\d{1,3}$/', $valor) === 1)) && (int) $valor >= 0 && (int) $valor <= 150) {
            $edades[$campo] = (int) $valor;
        } else {
            return ['fallo' => ['filtro_edad_invalida', "$campo debe ser un entero entre 0 y 150"]];
        }
    }
    if ($edades['age_min'] !== null && $edades['age_max'] !== null && $edades['age_min'] > $edades['age_max']) {
        return ['fallo' => ['filtro_edad_invalida', 'age_min no puede ser mayor que age_max']];
    }
    $gender = $body['gender'] ?? null;
    if ($gender === '') {
        $gender = null;
    }
    if ($gender !== null && (!is_string($gender) || !in_array($gender, ['M', 'F', 'O'], true))) {
        return ['fallo' => ['filtro_genero_invalido', 'gender debe ser M, F, O o vacío']];
    }
    $tipo = $body['tipo_accion'] ?? null;
    if ($tipo === '') {
        $tipo = null;
    }
    if ($tipo !== null && (!is_string($tipo) || mb_strlen($tipo) > 30)) {
        return ['fallo' => ['filtro_tipo_invalido', 'tipo_accion debe ser un texto de hasta 30 caracteres']];
    }

    return ['valores' => ['scope' => $scope, 'age_min' => $edades['age_min'], 'age_max' => $edades['age_max'], 'gender' => $gender, 'tipo_accion' => $tipo]];
}

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

        if ($nombre === '') {
            api_error(400, 'filtro_nombre_requerido', 'falta nombre del filtro');
            return;
        }
        if (mb_strlen($nombre) > 100) {
            api_error(400, 'filtro_nombre_largo', 'el nombre no puede superar los 100 caracteres');
            return;
        }

        $validado = validar_filtro_guardado($body);
        if (isset($validado['fallo'])) {
            api_error(400, $validado['fallo'][0], $validado['fallo'][1]);
            return;
        }
        ['scope' => $scope, 'age_min' => $ageMin, 'age_max' => $ageMax, 'gender' => $gender, 'tipo_accion' => $tipoAccion] = $validado['valores'];

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
