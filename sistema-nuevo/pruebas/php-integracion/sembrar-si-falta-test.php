<?php

declare(strict_types=1);

/** @var PDO $pdo */

require_once __DIR__ . '/../../datos/generador/base-sembrada.php';

// Sembrar (generar-datos-semilla.php) hace DROP de TODAS las tablas, también las que el
// usuario escribe desde el panel (notas, filtros guardados, config de alertas). ejecutar.sh
// sembraba en cada arranque, así que cada reinicio los borraba. sembrar-si-falta.php, que es
// lo que corre ahora, no hace nada si la base ya está sembrada.
$datos = __DIR__ . '/../../datos';

assert_igual(null, motivo_para_sembrar($pdo, $datos), 'siembra: la base ya sembrada no necesita sembrarse');

// Qué tiene que decir el diagnóstico según lo que falte: en un schema descartable dentro de
// una transacción que SIEMPRE se revierte (mismo recurso que esquema-datos-ejemplo-test.php).
$motivos = array_fill_keys(['vacia', 'sin_acciones', 'sembrada', 'esquema_viejo'], 'no calculado');
$error = null;
$pdo->beginTransaction();
try {
    $pdo->exec('CREATE SCHEMA siembra_prueba');
    $pdo->exec('SET LOCAL search_path TO siembra_prueba');
    $motivos['vacia'] = motivo_para_sembrar($pdo, $datos);

    foreach (['esquema.sql', 'esquema-nucleo.sql'] as $archivo) {
        $pdo->exec((string) file_get_contents("$datos/$archivo"));
    }
    $motivos['sin_acciones'] = motivo_para_sembrar($pdo, $datos);

    $pdo->exec((string) file_get_contents("$datos/esquema-datos-ejemplo.sql"));
    $motivos['sembrada'] = motivo_para_sembrar($pdo, $datos);

    // Un esquema de una versión anterior: le falta una tabla que el código de hoy necesita.
    $pdo->exec('DROP TABLE intentos_login');
    $motivos['esquema_viejo'] = motivo_para_sembrar($pdo, $datos);
} catch (PDOException $e) {
    $error = $e->getMessage();
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
assert_igual(null, $error, 'siembra: los esquemas cargan sin error en el schema descartable');
assert_verdadero(str_contains((string) $motivos['vacia'], 'vacía'), 'siembra: sin tablas, el motivo es que la base está vacía');
assert_verdadero(str_contains((string) $motivos['sin_acciones'], 'acciones'), 'siembra: con las tablas pero sin acciones, hay que sembrar');
assert_igual(null, $motivos['sembrada'], 'siembra: tablas + acciones cargadas = sembrada');
assert_verdadero(
    str_contains((string) $motivos['esquema_viejo'], 'intentos_login'),
    'siembra: si falta una tabla del esquema, el motivo nombra cuál (el esquema cambió desde la última siembra)'
);
$existe = $pdo->query("SELECT COUNT(*) FROM pg_namespace WHERE nspname = 'siembra_prueba'")->fetchColumn();
assert_igual(0, (int) $existe, 'siembra: el rollback no deja el schema descartable');

// El comando real, sobre la base real, con trabajo del usuario encima: no lo toca.
$almacenConfig = new AlmacenConfiguracion($pdo);
$configOriginal = $almacenConfig->obtenerTodos();
$accionId = (string) $pdo->query('SELECT id FROM acciones ORDER BY id LIMIT 1')->fetchColumn();
$nombreFiltro = '__filtro_que_tiene_que_sobrevivir__';

try {
    $pdo->prepare('INSERT INTO notas_acciones (accion_id, texto) VALUES (?, ?) ON CONFLICT (accion_id) DO UPDATE SET texto = EXCLUDED.texto')
        ->execute([$accionId, 'nota que tiene que sobrevivir']);
    $pdo->prepare("INSERT INTO filtros_guardados (nombre, scope) VALUES (?, 'all_countries')")->execute([$nombreFiltro]);
    $almacenConfig->guardar('umbral_sensibilidad', '73');

    $proceso = proc_open(
        [PHP_BINARY, __DIR__ . '/../../datos/sembrar-si-falta.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    $salida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $codigo = proc_close($proceso);

    assert_igual(0, $codigo, 'sembrar-si-falta.php: termina bien con la base ya sembrada');
    assert_verdadero(str_contains($salida, 'ya están'), 'sembrar-si-falta.php: avisa que no volvió a sembrar');

    $stmt = $pdo->prepare('SELECT texto FROM notas_acciones WHERE accion_id = ?');
    $stmt->execute([$accionId]);
    assert_igual('nota que tiene que sobrevivir', $stmt->fetchColumn() ?: null, 'sembrar-si-falta.php: la nota guardada sobrevive');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM filtros_guardados WHERE nombre = ?');
    $stmt->execute([$nombreFiltro]);
    assert_igual(1, (int) $stmt->fetchColumn(), 'sembrar-si-falta.php: el filtro guardado sobrevive');

    assert_igual(73, $almacenConfig->obtenerUmbral(), 'sembrar-si-falta.php: la configuración de alertas sobrevive');
} finally {
    // Si la siembra sí corrió (el bug que esta prueba vigila), la base quedó recreada: se
    // limpia lo que cualquiera de los dos caminos pudo dejar, sin tocar el resto.
    $pdo->prepare('DELETE FROM notas_acciones WHERE accion_id = ?')->execute([$accionId]);
    $pdo->prepare('DELETE FROM filtros_guardados WHERE nombre = ?')->execute([$nombreFiltro]);
    $pdo->exec('DELETE FROM configuracion_alertas');
    foreach ($configOriginal as $clave => $valor) {
        $almacenConfig->guardar($clave, $valor);
    }
}
