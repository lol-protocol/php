<?php

declare(strict_types=1);

// saneador_accion: orquesta todo el saneamiento de un registro crudo completo.
$contexto = [
    'usuarios_por_id' => ['u001' => ['country' => 'AR']],
    'monedas_validas' => ['USD', 'ARS'],
    'moneda_por_pais' => ['AR' => 'ARS'],
    'tasa_por_moneda' => ['USD' => 1.0, 'ARS' => 1000.0],
    'etiquetas' => ['login' => 'Inicio de sesión', 'payment' => 'Pago', 'review_submit' => 'Reseña enviada'],
    'rutas' => ['login' => '/app/auth/iniciar-sesion.php'],
    'paises_validos' => ['AR', 'US'],
    'offset_por_pais' => ['AR' => -3],
];

$base = ['user_id' => 'u001', 'type' => 'login', 'timestamp' => '2026-09-03T12:00:00Z', 'duration_ms' => 1000];

$valido = saneador_accion($base + ['id' => 'a1'], $contexto);
assert_verdadero($valido !== null, 'accion: registro válido no se descarta');
assert_igual('login', $valido['type'] ?? null, 'accion: conserva el tipo saneado');
assert_igual('Inicio de sesión', $valido['label'] ?? null, 'accion: resuelve la etiqueta desde el contexto');

$sinDuracion = ['user_id' => 'u001', 'type' => 'login', 'timestamp' => '2026-09-03T12:00:00Z'];
assert_igual(null, saneador_accion($sinDuracion, $contexto), 'accion: sin duration_ms (campo esencial) -> se descarta');

$usuarioInexistente = ['user_id' => 'u999', 'type' => 'login', 'timestamp' => '2026-09-03T12:00:00Z', 'duration_ms' => 1000];
assert_igual(null, saneador_accion($usuarioInexistente, $contexto), 'accion: usuario no existe en el contexto -> se descarta');

// El comentario (solo aplica a review_submit/support_ticket) llega limpio, sin <script>.
$conComentario = saneador_accion(
    ['user_id' => 'u001', 'type' => 'review_submit', 'timestamp' => '2026-09-03T12:00:00Z', 'duration_ms' => 1000,
     'comment' => '<script>alert(1)</script>todo bien'],
    $contexto
);
assert_igual('alert(1)todo bien', $conComentario['comment'] ?? null, 'accion: comentario saneado sin <script>');

// Moneda inválida en un pago: no se descarta el monto, se asume la del país del usuario.
$pagoMonedaInvalida = saneador_accion(
    ['user_id' => 'u001', 'type' => 'payment', 'timestamp' => '2026-09-03T12:00:00Z', 'duration_ms' => 1000,
     'amount' => '50', 'currency' => 'XXX'],
    $contexto
);
assert_igual('ARS', $pagoMonedaInvalida['currency'] ?? null, 'accion: moneda inválida cae al país del usuario');
assert_igual(0.05, $pagoMonedaInvalida['amount_usd'] ?? null, 'accion: convierte a USD con la tasa del contexto');

// La hora local de la IP se deriva del offset del país de la IP sobre el timestamp UTC.
$conIp = saneador_accion(
    $base + ['ip' => '200.1.2.3:8080', 'ip_country' => 'ar', 'ip_isp' => 'Fibertel'],
    $contexto
);
assert_igual('200.1.2.3', $conIp['ip'] ?? null, 'accion: IP saneada, sin el puerto colado');
assert_igual('AR', $conIp['ip_country'] ?? null, 'accion: país de IP normalizado a mayúsculas');
assert_igual('09:00:00', $conIp['ip_local_time'] ?? null, 'accion: hora local = UTC + offset del país (AR = -3h)');
