<?php

declare(strict_types=1);

/** @var PDO $pdo */
$ip = '203.0.113.__test__'; // TEST-NET-3 (RFC 5737): no es una IP real, no colisiona con nada
$almacen = new AlmacenIntentosLogin($pdo);

$almacen->limpiar($ip); // por si quedó sucio de una corrida anterior interrumpida

assert_igual(null, $almacen->bloqueadaHasta($ip), 'intentos_login: IP sin historial no está bloqueada');

for ($i = 0; $i < 4; $i++) {
    assert_igual(null, $almacen->registrarFallo($ip), 'intentos_login: registrarFallo bajo el límite devuelve null (no bloqueada)');
}
assert_igual(null, $almacen->bloqueadaHasta($ip), 'intentos_login: con 4 fallos (bajo el límite de 5) todavía no bloquea');

// El 5to fallo es el que cruza el umbral: bloquea para la PRÓXIMA vez, pero
// sigue siendo "contraseña incorrecta" para sí mismo (null), no un bloqueo
// retroactivo sobre su propio intento.
assert_igual(null, $almacen->registrarFallo($ip), 'intentos_login: el propio 5to fallo (el que cruza el umbral) sigue devolviendo null');
assert_verdadero($almacen->bloqueadaHasta($ip) !== null, 'intentos_login: pero el estado ya queda bloqueado para la próxima lectura');

// Un 6to fallo SÍ debe reportarse bloqueado -- y, a diferencia de bloqueadaHasta()
// (una lectura aparte que bajo concurrencia puede quedar stale), acá viene del
// mismo RETURNING atómico del UPDATE que registra el fallo (ver AlmacenIntentosLogin::registrarFallo).
assert_verdadero($almacen->registrarFallo($ip) !== null, 'intentos_login: un 6to fallo ya reporta el bloqueo en el mismo RETURNING, no en una lectura aparte');

$almacen->limpiar($ip);
assert_igual(null, $almacen->bloqueadaHasta($ip), 'intentos_login: limpiar resetea el bloqueo (login exitoso)');

// Los fallos cuentan dentro de una ventana: pasado el bloqueo, un solo fallo NO vuelve a bloquear.
for ($i = 0; $i < 5; $i++) {
    $almacen->registrarFallo($ip);
}
assert_verdadero($almacen->bloqueadaHasta($ip) !== null, 'intentos_login: 5 fallos bloquean');
$pdo->prepare("UPDATE intentos_login SET ultimo_intento = NOW() - INTERVAL '20 minutes', bloqueado_hasta = NOW() - INTERVAL '5 minutes' WHERE ip = ?")->execute([$ip]);
assert_igual(null, $almacen->bloqueadaHasta($ip), 'intentos_login: vencido el bloqueo, la IP ya no está bloqueada');
assert_igual(null, $almacen->registrarFallo($ip), 'intentos_login: un fallo después del bloqueo no devuelve bloqueo');
assert_igual(null, $almacen->bloqueadaHasta($ip), 'intentos_login: ni vuelve a bloquear: el contador arranca otra vez en 1');
$cuenta = $pdo->prepare('SELECT intentos FROM intentos_login WHERE ip = ?');
$cuenta->execute([$ip]);
assert_igual(1, (int) $cuenta->fetchColumn(), 'intentos_login: el contador volvió a 1 tras vencer el bloqueo');

// Cuatro fallos de hace horas más uno nuevo tampoco bloquean ("5 fallos consecutivos", no 5 en la vida).
$almacen->limpiar($ip);
$pdo->prepare("INSERT INTO intentos_login (ip, intentos, ultimo_intento) VALUES (?, 4, NOW() - INTERVAL '3 hours')")->execute([$ip]);
assert_igual(null, $almacen->registrarFallo($ip), 'intentos_login: 4 fallos viejos + 1 nuevo no bloquean');
assert_igual(null, $almacen->bloqueadaHasta($ip), 'intentos_login: el fallo de ahora cuenta como el primero');

$almacen->limpiar($ip);

// podarViejos(): la tabla no tiene cron que la limpie sola (ver comentario en
// registrarFallo), así que esto es lo único que evita que crezca para siempre.
$ipVieja = '203.0.113.__test_vieja__';
$ipReciente = '203.0.113.__test_reciente__';
$pdo->prepare("INSERT INTO intentos_login (ip, intentos, ultimo_intento) VALUES (?, 1, NOW() - INTERVAL '25 hours')")->execute([$ipVieja]);
$pdo->prepare("INSERT INTO intentos_login (ip, intentos, ultimo_intento) VALUES (?, 1, NOW())")->execute([$ipReciente]);

$almacen->podarViejos();

$existe = function (string $ip) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM intentos_login WHERE ip = ?');
    $stmt->execute([$ip]);
    return $stmt->fetchColumn() !== false;
};
assert_igual(false, $existe($ipVieja), 'intentos_login: podarViejos borra filas con ultimo_intento más viejo que RETENCION_HORAS');
assert_igual(true, $existe($ipReciente), 'intentos_login: podarViejos NO borra filas recientes');

$pdo->prepare('DELETE FROM intentos_login WHERE ip = ?')->execute([$ipReciente]);
