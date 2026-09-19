<?php

declare(strict_types=1);

/**
 * No depende de que el microservicio Java esté corriendo: apunta a un puerto
 * que nadie usa, así que reproduce de forma determinística el caso "el
 * servicio no respondió" sin importar el entorno donde corra esta suite.
 */
$clienteInalcanzable = new ClienteEstadisticas('http://localhost:8099');

$inicio = microtime(true);
$resultado = $clienteInalcanzable->stats('login', null, 0, 150, 'all', null);
$duracionMs = (microtime(true) - $inicio) * 1000;

assert_igual(null, $resultado, 'ClienteEstadisticas: servicio inalcanzable devuelve null (no lanza excepción)');

// Debe reintentar (tarda más que 0) pero sin acumular timeouts completos de más
// de un intento (2s conexión + 3s transferencia cada uno): si esto tardara
// varios segundos, alguien habría subido MAX_INTENTOS o el timeout por intento
// sin pensar en el costo para el usuario esperando /api/timeline.
assert_verdadero($duracionMs < 2000, "ClienteEstadisticas: reintentar un servicio caído no debe tardar segundos (tardó {$duracionMs}ms)");
