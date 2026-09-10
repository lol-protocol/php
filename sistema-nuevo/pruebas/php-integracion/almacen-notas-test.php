<?php

declare(strict_types=1);

/** @var PDO $pdo */
$almacen = new AlmacenNotas($pdo);

$accionId = $pdo->query('SELECT id FROM acciones LIMIT 1')->fetchColumn();
assert_verdadero($accionId !== false, 'notas: hay al menos una acción sembrada para probar contra ella');

$leerTexto = function (PDO $pdo, string $accionId): ?string {
    $stmt = $pdo->prepare('SELECT texto FROM notas_acciones WHERE accion_id = ?');
    $stmt->execute([$accionId]);
    $valor = $stmt->fetchColumn();
    return $valor === false ? null : $valor;
};

$almacen->guardar($accionId, 'nota de prueba');
assert_igual('nota de prueba', $leerTexto($pdo, $accionId), 'notas: guardar crea la fila con el texto dado');

$almacen->guardar($accionId, 'nota actualizada');
assert_igual('nota actualizada', $leerTexto($pdo, $accionId), 'notas: guardar de nuevo actualiza (upsert), no duplica fila');

$almacen->guardar($accionId, '   ');
assert_igual(null, $leerTexto($pdo, $accionId), 'notas: guardar con texto en blanco borra la nota en vez de guardar espacios');

$almacen->guardar($accionId, 'otra vez');
$almacen->eliminar($accionId);
assert_igual(null, $leerTexto($pdo, $accionId), 'notas: eliminar borra la fila');

// Limpieza por si algún assert de arriba falló antes de eliminar.
$almacen->eliminar($accionId);
