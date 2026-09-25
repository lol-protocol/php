<?php

declare(strict_types=1);

/** @var PDO $pdo */
$almacen = new AlmacenFiltros($pdo);

$id = $almacen->crear('__test_filtro__', 'preset:otan', 18, 30, 'M', 'payment');
assert_verdadero($id > 0, 'filtros: crear devuelve un id positivo');

$creado = $almacen->obtener($id);
assert_igual('__test_filtro__', $creado['nombre'], 'filtros: obtener devuelve el nombre guardado');
assert_igual('preset:otan', $creado['scope'], 'filtros: obtener conserva el scope tal cual (sin traducir prefijos)');
assert_igual(18, (int) $creado['age_min'], 'filtros: obtener conserva age_min');
assert_igual('payment', $creado['tipo_accion'], 'filtros: obtener conserva tipo_accion');

$todos = $almacen->obtenerTodos();
$nombres = array_column($todos, 'nombre');
assert_verdadero(in_array('__test_filtro__', $nombres, true), 'filtros: obtenerTodos incluye el recién creado');

$idInexistente = $id + 999999;
assert_igual(null, $almacen->obtener($idInexistente), 'filtros: obtener con id inexistente devuelve null');

$eliminado = $almacen->eliminar($id);
assert_verdadero($eliminado, 'filtros: eliminar un filtro existente devuelve true');
assert_igual(null, $almacen->obtener($id), 'filtros: tras eliminar, obtener devuelve null');
assert_verdadero(!$almacen->eliminar($id), 'filtros: eliminar de nuevo (ya no existe) devuelve false');

// null en age_min/age_max/gender/tipo_accion es un caso real: "todos" en ese filtro.
$idNulos = $almacen->crear('__test_filtro_nulos__', 'all_countries', null, null, null, null);
$conNulos = $almacen->obtener($idNulos);
assert_igual(null, $conNulos['age_min'], 'filtros: age_min null se guarda y se lee como null');
assert_igual(null, $conNulos['tipo_accion'], 'filtros: tipo_accion null se guarda y se lee como null');
$almacen->eliminar($idNulos);
