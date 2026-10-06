<?php

declare(strict_types=1);

// Los ayudantes de marco-pruebas.php que usan las demás pruebas para correr procesos, levantar servidores falsos y
// vigilar el código fuente. Si alguno devolviera "nada" en silencio, las pruebas que se apoyan en él seguirían pasando
// sin probar nada: por eso tienen las suyas.

// ejecutar_proceso: lo que escribe (salida y errores juntas), su código de salida, su entorno y su directorio.
$r = ejecutar_proceso([PHP_BINARY, '-r', 'echo "hola "; fwrite(STDERR, "error"); exit(3);']);
assert_igual(['hola error', 3], [$r['salida'], $r['codigo']], 'ejecutar_proceso: junta la salida con la de errores y devuelve el código de salida');
$r = ejecutar_proceso([PHP_BINARY, '-r', 'echo getenv("DE_LA_PRUEBA") . "|" . basename(getcwd());'], sys_get_temp_dir(), ['DE_LA_PRUEBA' => 'si', 'PATH' => (string) getenv('PATH')]);
assert_igual('si|' . basename(sys_get_temp_dir()), $r['salida'], 'ejecutar_proceso: pasa el entorno y corre en el directorio pedido');
assert_igual(0, ejecutar_proceso([PHP_BINARY, '-r', 'exit(0);'])['codigo'], 'ejecutar_proceso: un proceso que termina bien devuelve 0');

// Con mucha salida por errores no se traba: una tubería se llena a los ~64 KB y el proceso que escribe se queda esperando
// a que alguien la lea, así que leer primero toda la salida y después los errores era un callejón sin salida. El "timeout 5"
// es para que, si vuelve a pasar, la prueba falle (código 124) en vez de colgar la suite.
$r = ejecutar_proceso(['timeout', '5', PHP_BINARY, '-r', 'fwrite(STDERR, str_repeat("e", 300000)); echo "fin";']);
assert_igual([0, 300003], [$r['codigo'], strlen($r['salida'])], 'ejecutar_proceso: 300 KB por la salida de errores no lo traban');
$r = ejecutar_proceso([PHP_BINARY, '-r', 'echo "a"; fwrite(STDERR, "b"); echo "c";']);
assert_igual('abc', $r['salida'], 'ejecutar_proceso: la salida y los errores salen en el orden en que se escribieron');

// puerto_libre y servidor_colgado.
$puerto = puerto_libre();
assert_verdadero($puerto > 1023, "puerto_libre: un puerto de usuario ($puerto)");
$socket = stream_socket_server("tcp://127.0.0.1:$puerto", $codigo, $mensaje);
assert_verdadero($socket !== false, 'puerto_libre: el puerto se puede abrir enseguida');
if ($socket) {
    fclose($socket);
}

[$colgado, $puertoColgado] = servidor_colgado();
$cliente = @fsockopen('127.0.0.1', $puertoColgado, $codigo, $mensaje, 1);
assert_verdadero($cliente !== false, 'servidor_colgado: acepta la conexión');
if ($cliente) {
    stream_set_timeout($cliente, 0, 300_000);
    fwrite($cliente, "GET / HTTP/1.0\r\n\r\n");
    assert_igual('', (string) fread($cliente, 100), 'servidor_colgado: nunca responde');
    fclose($cliente);
}
fclose($colgado);

// levantar_servidor_php / detener_servidor_php.
$router = tempnam(sys_get_temp_dir(), 'router-prueba-');
file_put_contents($router, '<?php echo "respuesta de " . getenv("DE_LA_PRUEBA");');
$servidor = levantar_servidor_php($router, ['DE_LA_PRUEBA' => 'la prueba']);
assert_igual('respuesta de la prueba', @file_get_contents("http://127.0.0.1:{$servidor['puerto']}/"), 'levantar_servidor_php: ya atiende pedidos cuando devuelve, con el entorno pedido');
detener_servidor_php($servidor);
assert_verdadero(@fsockopen('127.0.0.1', $servidor['puerto'], $codigo, $mensaje, 1) === false, 'detener_servidor_php: el puerto queda sin nadie');
unlink($router);

// literales_de_texto: los textos entre comillas, no los comentarios.
$codigo = '<?php /* "en un comentario" */ $a = \'uno\'; $b = "dos"; // \'tres\'' . "\n" . '$c = "d$a";';
assert_igual(['uno', 'dos'], literales_de_texto($codigo), 'literales_de_texto: solo los textos entre comillas, sin comentarios ni los que interpolan variables');

// archivos_con_patron / sin_funcion: dónde aparece un patrón, descontando el cuerpo de un helper.
$fuentes = [
    'a.php' => "<?php\nfunction ayudante() {\n    return json_encode(1);\n}\n",
    'b.php' => "<?php\n\$x = json_encode(2);\n",
];
assert_igual(['a.php', 'b.php'], archivos_con_patron($fuentes, '/json_encode\(/'), 'archivos_con_patron: todos los archivos donde aparece');
assert_igual(['b.php'], archivos_con_patron($fuentes, '/json_encode\(/', ['ayudante']), 'archivos_con_patron: sin contar el cuerpo del helper permitido');
assert_igual("<?php\n", sin_funcion($fuentes['a.php'], 'ayudante'), 'sin_funcion: saca la función entera');
