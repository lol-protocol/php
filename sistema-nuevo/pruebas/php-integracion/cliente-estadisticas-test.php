<?php

declare(strict_types=1);

/**
 * No depende de que el microservicio Java esté corriendo: apunta a un puerto
 * que nadie usa, así que reproduce de forma determinística el caso "el
 * servicio no respondió" sin importar el entorno donde corra esta suite.
 */
$clienteInalcanzable = new ClienteEstadisticas('http://localhost:8099');

$inicio = microtime(true);
$resultado = $clienteInalcanzable->statsVarios(['login'], null, 0, 150, 'all', null);
$duracionMs = (microtime(true) - $inicio) * 1000;

assert_igual(['login' => null], $resultado, 'ClienteEstadisticas: servicio inalcanzable devuelve null (no lanza excepción)');

// Un puerto sin nadie escuchando rechaza la conexión al instante: no hay nada que esperar ni que reintentar.
// Si esto tardara segundos, alguien habría subido el timeout o agregado esperas sin pensar en el costo
// para el usuario esperando /api/timeline.
assert_verdadero($duracionMs < 2000, "ClienteEstadisticas: un servicio caído no debe tardar segundos (tardó {$duracionMs}ms)");

// Colgado (acepta la conexión pero nunca responde) es peor que caído: cada pedido espera el timeout
// completo. Solo el primero de la instancia debe pagar esa espera; los siguientes cortan de inmediato.
[$servidorColgado, $puertoColgado] = servidor_colgado();
$clienteColgado = new ClienteEstadisticas("http://127.0.0.1:$puertoColgado");

$inicio = microtime(true);
$resultados = array_map(
    fn (string $tipo) => $clienteColgado->statsVarios([$tipo], null, 0, 150, 'all', null)[$tipo],
    ['login', 'payment', 'search']
);
$duracionMs = (microtime(true) - $inicio) * 1000;
fclose($servidorColgado);

assert_igual([null, null, null], $resultados, 'ClienteEstadisticas: servicio colgado devuelve null para todos los tipos');
// Un timeout son ~3s: con el corte, 3 pedidos tardan ~3s; sin él, ~9s. 6s deja margen a los dos lados.
assert_verdadero($duracionMs < 6000, "ClienteEstadisticas: con el servicio colgado, solo el primer pedido espera el timeout (tardó {$duracionMs}ms)");

// Varios tipos a la vez: se piden en un solo lote paralelo (curl_multi) en vez de
// secuencial -- /api/timeline lo llama una sola vez con los tipos distintos de la
// página, en vez de una llamada por tipo.
[$servidorColgadoLote, $puertoColgadoLote] = servidor_colgado();
$clienteColgadoLote = new ClienteEstadisticas("http://127.0.0.1:$puertoColgadoLote");

$tiposLote = ['login', 'payment', 'search', 'view', 'upload'];
$inicio = microtime(true);
$resultadosLote = $clienteColgadoLote->statsVarios($tiposLote, null, 0, 150, 'all', null);
$duracionLoteMs = (microtime(true) - $inicio) * 1000;
fclose($servidorColgadoLote);

assert_igual(
    array_fill_keys($tiposLote, null),
    $resultadosLote,
    'ClienteEstadisticas: statsVarios() con servicio colgado devuelve null para todos los tipos del lote'
);
// Pedidos secuenciales (uno por uno, como antes) tardarían ~3s el primero y
// después nada (sinRespuesta corta el resto) -- ~3s también, así que esa parte
// sola no distinguiría paralelo de secuencial. Lo que sí lo distingue es que acá
// entran 5 tipos: si statsVarios() los pidiera de a uno con su propio timeout
// en vez de un solo lote paralelo, tardaría varios múltiplos de 3s.
assert_verdadero(
    $duracionLoteMs < 4500,
    "ClienteEstadisticas: statsVarios() debe pedir todos los tipos en paralelo, no uno por uno (tardó {$duracionLoteMs}ms con 5 tipos)"
);

assert_igual([], (new ClienteEstadisticas('http://localhost:8099'))->statsVarios([], null, 0, 150, 'all', null), 'ClienteEstadisticas: statsVarios() con lista vacía de tipos devuelve []');
