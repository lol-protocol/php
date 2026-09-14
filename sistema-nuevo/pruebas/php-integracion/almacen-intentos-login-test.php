<?php

declare(strict_types=1);

/** @var PDO $pdo */
$ip = '203.0.113.__test__'; // TEST-NET-3 (RFC 5737): no es una IP real, no colisiona con nada

AlmacenIntentosLogin::limpiar($pdo, $ip); // por si quedó sucio de una corrida anterior interrumpida

assert_igual(null, AlmacenIntentosLogin::bloqueadaHasta($pdo, $ip), 'intentos_login: IP sin historial no está bloqueada');

for ($i = 0; $i < 4; $i++) {
    AlmacenIntentosLogin::registrarFallo($pdo, $ip);
}
assert_igual(null, AlmacenIntentosLogin::bloqueadaHasta($pdo, $ip), 'intentos_login: con 4 fallos (bajo el límite de 5) todavía no bloquea');

AlmacenIntentosLogin::registrarFallo($pdo, $ip); // 5to fallo: cruza el umbral
assert_verdadero(AlmacenIntentosLogin::bloqueadaHasta($pdo, $ip) !== null, 'intentos_login: al 5to fallo queda bloqueada');

AlmacenIntentosLogin::limpiar($pdo, $ip);
assert_igual(null, AlmacenIntentosLogin::bloqueadaHasta($pdo, $ip), 'intentos_login: limpiar resetea el bloqueo (login exitoso)');

// podarViejos(): la tabla no tiene cron que la limpie sola (ver comentario en
// registrarFallo), así que esto es lo único que evita que crezca para siempre.
$ipVieja = '203.0.113.__test_vieja__';
$ipReciente = '203.0.113.__test_reciente__';
$pdo->prepare("INSERT INTO intentos_login (ip, intentos, ultimo_intento) VALUES (?, 1, NOW() - INTERVAL '25 hours')")->execute([$ipVieja]);
$pdo->prepare("INSERT INTO intentos_login (ip, intentos, ultimo_intento) VALUES (?, 1, NOW())")->execute([$ipReciente]);

AlmacenIntentosLogin::podarViejos($pdo);

$existe = function (string $ip) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM intentos_login WHERE ip = ?');
    $stmt->execute([$ip]);
    return $stmt->fetchColumn() !== false;
};
assert_igual(false, $existe($ipVieja), 'intentos_login: podarViejos borra filas con ultimo_intento más viejo que RETENCION_HORAS');
assert_igual(true, $existe($ipReciente), 'intentos_login: podarViejos NO borra filas recientes');

$pdo->prepare('DELETE FROM intentos_login WHERE ip = ?')->execute([$ipReciente]);
