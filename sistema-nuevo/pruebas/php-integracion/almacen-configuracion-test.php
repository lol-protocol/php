<?php

declare(strict_types=1);

/** @var PDO $pdo */
$almacen = new AlmacenConfiguracion($pdo);

assert_igual(null, $almacen->obtener('__clave_que_no_existe__'), 'config: obtener una clave inexistente devuelve null');

$almacen->guardar('__test_clave__', 'valor1');
assert_igual('valor1', $almacen->obtener('__test_clave__'), 'config: guardar+obtener devuelve lo guardado');
$almacen->guardar('__test_clave__', 'valor2');
assert_igual('valor2', $almacen->obtener('__test_clave__'), 'config: guardar de nuevo actualiza (upsert), no falla por PK duplicada');
$pdo->prepare('DELETE FROM configuracion_alertas WHERE clave = ?')->execute(['__test_clave__']);

// esAlertaHabilitada/obtenerUmbral tocan claves reales del sistema (alerta_*,
// umbral_sensibilidad) -- se guarda el valor original y se restaura al final
// para no dejar la configuración de alertas distinta a como estaba.
$original = $almacen->obtenerTodos();

assert_igual(true, $almacen->esAlertaHabilitada('__tipo_sin_config__'), 'config: tipo sin fila en la tabla se considera habilitado por defecto');

$almacen->guardar('alerta_ip_pais', 'false');
assert_igual(false, $almacen->esAlertaHabilitada('ip_pais'), 'config: alerta_ip_pais=false deshabilita ese tipo');
$almacen->guardar('alerta_ip_pais', 'true');
assert_igual(true, $almacen->esAlertaHabilitada('ip_pais'), 'config: alerta_ip_pais=true lo vuelve a habilitar');

$almacen->guardar('umbral_sensibilidad', '80');
assert_igual(80, $almacen->obtenerUmbral(), 'config: obtenerUmbral lee el valor guardado como int');

// Caso trampa: "0" es falsy en PHP -- un obtenerUmbral() escrito como
// "$valor ? (int)$valor : 50" leería sensibilidad=0 como si fuera 50.
$almacen->guardar('umbral_sensibilidad', '0');
assert_igual(0, $almacen->obtenerUmbral(), 'config: sensibilidad guardada en 0 se lee como 0, no como el default 50');

foreach ($original as $clave => $valor) {
    $almacen->guardar($clave, $valor);
}
