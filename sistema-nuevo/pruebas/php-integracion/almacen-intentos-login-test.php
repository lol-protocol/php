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
