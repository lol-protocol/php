<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/api/filtros.php';

// POST /api/filtros: un tipo equivocado o un texto más largo que la columna daba 500; ahora es un 400 con código.
$bien = validar_filtro_guardado(['scope' => 'country:US', 'age_min' => 18, 'age_max' => '65', 'gender' => 'F', 'tipo_accion' => 'login']);
assert_igual(['scope' => 'country:US', 'age_min' => 18, 'age_max' => 65, 'gender' => 'F', 'tipo_accion' => 'login'], $bien['valores'], 'filtros: un cuerpo válido se acepta y las edades quedan enteras');

$vacio = validar_filtro_guardado(['age_min' => null, 'age_max' => '', 'gender' => '', 'tipo_accion' => '']);
assert_igual(['scope' => 'all_countries', 'age_min' => null, 'age_max' => null, 'gender' => null, 'tipo_accion' => null], $vacio['valores'], 'filtros: vacío significa "todos" (null), y el alcance por defecto es all_countries');

foreach ([
    ['scope' => ['x'], 'filtro_scope_invalido', 'scope como arreglo'],
    ['scope' => str_repeat('a', 51), 'filtro_scope_invalido', 'scope de más de 50 caracteres'],
    ['age_min' => 'abc', 'filtro_edad_invalida', 'edad que no es número'],
    ['age_max' => 151, 'filtro_edad_invalida', 'edad sobre 150'],
    ['age_min' => -1, 'filtro_edad_invalida', 'edad negativa'],
    ['age_min' => 50, 'age_max' => 20, 'filtro_edad_invalida', 'edad mínima mayor que la máxima'],
    ['gender' => 'X', 'filtro_genero_invalido', 'género fuera de M, F y O'],
    ['gender' => ['M'], 'filtro_genero_invalido', 'género como arreglo'],
    ['tipo_accion' => str_repeat('a', 31), 'filtro_tipo_invalido', 'tipo de más de 30 caracteres'],
    ['tipo_accion' => ['login'], 'filtro_tipo_invalido', 'tipo como arreglo'],
] as $caso) {
    $descripcion = array_pop($caso);
    $codigo = array_pop($caso);
    $resultado = validar_filtro_guardado($caso);
    assert_igual($codigo, $resultado['fallo'][0] ?? null, "filtros: $descripcion se rechaza con $codigo");
}
