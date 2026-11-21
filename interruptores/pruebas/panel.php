<?php

declare(strict_types=1);

// Prueba en vivo del panel: arranca `php -S` sobre panel/ con una carpeta de datos temporal
// y recorre los casos por HTTP (login, roles, CSRF, bloqueo, cierre de sesión).
//   php pruebas/panel.php

require_once __DIR__ . '/../src/Usuarios.php';

use LolProtocol\Interruptores\Usuarios;

$raiz = dirname(__DIR__);
$var = sys_get_temp_dir() . '/interruptores-panel-' . bin2hex(random_bytes(4));
mkdir($var, 0775, true);
$fallos = 0;
$pasadas = 0;

function comprobar(string $nombre, bool $ok, string $detalle = ''): void
{
    global $fallos, $pasadas;
    if ($ok) {
        $pasadas++;
        return;
    }
    $fallos++;
    echo "FALLA: $nombre" . ($detalle !== '' ? " ($detalle)" : '') . "\n";
}

/**
 * Pide una URL y devuelve estado, cuerpo, cookies que el servidor manda y la redirección.
 *
 * @param array<string, string> $cookies
 * @param array<string, string> $formulario
 * @return array{estado: int, cuerpo: string, cookies: array<string, string>, ubicacion: string}
 */
function pedir(string $url, string $metodo = 'GET', array $cookies = [], array $formulario = []): array
{
    $cabeceras = [];
    if ($cookies !== []) {
        $partes = [];
        foreach ($cookies as $k => $v) {
            $partes[] = "$k=$v";
        }
        $cabeceras[] = 'Cookie: ' . implode('; ', $partes);
    }
    $contenido = '';
    if ($metodo === 'POST') {
        $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
        $contenido = http_build_query($formulario);
    }
    $ctx = stream_context_create(['http' => [
        'method' => $metodo,
        'header' => implode("\r\n", $cabeceras),
        'content' => $contenido,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 10,
    ]]);
    $cuerpo = (string) @file_get_contents($url, false, $ctx);
    $respuesta = ['estado' => 0, 'cuerpo' => $cuerpo, 'cookies' => [], 'ubicacion' => ''];
    foreach ($http_response_header ?? [] as $linea) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m)) {
            $respuesta['estado'] = (int) $m[1];
        } elseif (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $m)) {
            $respuesta['cookies'][$m[1]] = $m[2];
        } elseif (preg_match('/^Location:\s*(.+)$/i', $linea, $m)) {
            $respuesta['ubicacion'] = trim($m[1]);
        }
    }
    return $respuesta;
}

/** Aviso que el panel guardó para mostrar en la siguiente página (el texto va escapado). */
function aviso(string $html): string
{
    if (preg_match('/<p class="aviso">(.*?)<\/p>/s', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

function csrf(string $html): string
{
    return preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m) ? $m[1] : '';
}

// --- Usuarios de prueba (sin el panel en marcha) ----------------------------

$usr = new Usuarios($var . '/usuarios.json');
$usr->crear('ana', 'editor', 'clave-de-ana-123');
$usr->crear('luis', 'lector', 'clave-de-luis-456');
$usr->crear('mario', 'editor', 'clave-de-mario-321');

// --- Arranque del servidor --------------------------------------------------

$sock = stream_socket_server('tcp://127.0.0.1:0');
$puerto = (int) explode(':', stream_socket_get_name($sock, false))[1];
fclose($sock);

$proc = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$puerto}", '-t', "{$raiz}/panel"],
    [0 => ['pipe', 'r'], 1 => ['file', $var . '/servidor.log', 'w'], 2 => ['file', $var . '/servidor.log', 'w']],
    $pipes,
    null,
    ['INTERRUPTORES_VAR' => $var, 'INTERRUPTORES_CADUCIDAD' => '6', 'PATH' => getenv('PATH') ?: '/usr/bin:/bin'],
);
$base = "http://127.0.0.1:{$puerto}/index.php";
$listo = false;
for ($k = 0; $k < 50 && !$listo; $k++) {
    $s = @fsockopen('127.0.0.1', $puerto);
    if ($s !== false) {
        fclose($s);
        $listo = true;
    } else {
        usleep(100000);
    }
}
comprobar('el servidor de prueba arranca', $listo);

if ($listo) {
    // 1. Sin sesión, la página es el formulario de entrada.
    $r = pedir($base);
    comprobar('sin sesión se ve el formulario de entrada', $r['estado'] === 200 && str_contains($r['cuerpo'], 'name="usuario"'));

    // 2. Clave mala: mensaje genérico (no revela si el usuario existe).
    $r = pedir($base, 'POST', [], ['accion' => 'entrar', 'usuario' => 'ana', 'clave' => 'mala-clave-larga']);
    $c = $r['cookies'];
    $r2 = pedir($base, 'GET', $c);
    comprobar('clave mala: aviso genérico', aviso($r2['cuerpo']) === 'Usuario o clave incorrectos.');
    $r = pedir($base, 'POST', [], ['accion' => 'entrar', 'usuario' => 'nadie', 'clave' => 'mala-clave-larga']);
    $r2 = pedir($base, 'GET', $r['cookies']);
    comprobar('usuario que no existe: el mismo aviso genérico', aviso($r2['cuerpo']) === 'Usuario o clave incorrectos.');

    // 3. Editor entra; el panel muestra su rol y el registro de cambios.
    $r = pedir($base, 'POST', [], ['accion' => 'entrar', 'usuario' => 'ana', 'clave' => 'clave-de-ana-123']);
    $ana = $r['cookies'];
    comprobar('la entrada correcta redirige', $r['estado'] === 302 && $ana !== []);
    $p = pedir($base, 'GET', $ana);
    comprobar('el editor ve su nombre y su rol', str_contains($p['cuerpo'], 'ana · editor'));
    comprobar('el editor ve el registro de cambios', str_contains($p['cuerpo'], 'Últimos cambios'));
    $tokenAna = csrf($p['cuerpo']);
    comprobar('el editor tiene token CSRF', strlen($tokenAna) === 32);

    // 4. Un editor sí cambia algo, y queda anotado con su nombre.
    $r = pedir($base, 'POST', $ana, ['accion' => 'cambiar', 'csrf' => $tokenAna, 'ref' => 'gen.mascotas:gatitos', 'valor' => '0']);
    $p = pedir($base, 'GET', $ana);
    comprobar('el editor apaga un interruptor', aviso($p['cuerpo']) === 'Apagado: gen.mascotas:gatitos.');
    $estado = json_decode((string) @file_get_contents($var . '/estado.json'), true) ?: [];
    comprobar('el cambio queda en el estado', ($estado['gen.mascotas:gatitos'] ?? null) === false);
    comprobar('el cambio queda en el registro con su nombre',
        str_contains((string) @file_get_contents($var . '/cambios.jsonl'), '"quien":"ana"'));

    // 5. Una dependencia rota se rechaza y se explica.
    pedir($base, 'POST', $ana, ['accion' => 'cambiar', 'csrf' => $tokenAna, 'ref' => 'gen.filtro:textos', 'valor' => '0']);
    $p = pedir($base, 'GET', $ana);
    comprobar('apagar el filtro de textos se rechaza y dice qué lo necesita',
        str_contains(aviso($p['cuerpo']), 'no se puede apagar mientras estén encendidas'));

    // 6. CSRF: un token falso no cambia nada.
    pedir($base, 'POST', $ana, ['accion' => 'cambiar', 'csrf' => 'falso', 'ref' => 'gen.mascotas:gatitos', 'valor' => '1']);
    $p = pedir($base, 'GET', $ana);
    comprobar('un token CSRF falso se rechaza', aviso($p['cuerpo']) === 'La sesión venció: vuelve a intentarlo.');
    $estado = json_decode((string) @file_get_contents($var . '/estado.json'), true) ?: [];
    comprobar('y el estado no cambia', ($estado['gen.mascotas:gatitos'] ?? null) === false);

    // 7. Lector: ve el panel, pero el servidor rechaza cualquier cambio aunque el formulario esté a mano.
    $r = pedir($base, 'POST', [], ['accion' => 'entrar', 'usuario' => 'luis', 'clave' => 'clave-de-luis-456']);
    $luis = $r['cookies'];
    $p = pedir($base, 'GET', $luis);
    comprobar('el lector ve su rol', str_contains($p['cuerpo'], 'luis · lector'));
    comprobar('los botones del lector están bloqueados', substr_count($p['cuerpo'], ' disabled') >= 10);
    $tokenLuis = csrf($p['cuerpo']);
    pedir($base, 'POST', $luis, ['accion' => 'cambiar', 'csrf' => $tokenLuis, 'ref' => 'gen.mascotas:gatitos', 'valor' => '1']);
    $p = pedir($base, 'GET', $luis);
    comprobar('un lector que fuerza el envío no cambia nada (lo dice el servidor)',
        aviso($p['cuerpo']) === 'Tu rol (lector) no permite cambiar nada.');
    $estado = json_decode((string) @file_get_contents($var . '/estado.json'), true) ?: [];
    comprobar('el estado sigue igual tras el intento del lector', ($estado['gen.mascotas:gatitos'] ?? null) === false);

    // 8. Bloqueo: cinco claves malas seguidas bloquean; ni la buena entra hasta que pase el tiempo.
    for ($k = 0; $k < Usuarios::MAX_FALLOS; $k++) {
        pedir($base, 'POST', [], ['accion' => 'entrar', 'usuario' => 'luis', 'clave' => 'mala-clave-larga']);
    }
    $r = pedir($base, 'POST', [], ['accion' => 'entrar', 'usuario' => 'luis', 'clave' => 'clave-de-luis-456']);
    $p = pedir($base, 'GET', $r['cookies']);
    comprobar('tras cinco fallos, el aviso dice que hay que esperar', str_contains(aviso($p['cuerpo']), 'Demasiados intentos'));
    comprobar('y no entra ni con la clave buena', !str_contains($p['cuerpo'], 'luis · lector'));

    // 9. Cerrar sesión: el panel vuelve al formulario y la sesión anterior ya no sirve.
    pedir($base, 'POST', $ana, ['accion' => 'salir']);
    $p = pedir($base, 'GET', $ana);
    comprobar('salir sin token CSRF no cierra la sesión', str_contains($p['cuerpo'], 'Últimos cambios'));
    pedir($base, 'POST', $ana, ['accion' => 'salir', 'csrf' => $tokenAna]);
    $p = pedir($base, 'GET', $ana);
    comprobar('cerrar sesión con token devuelve al formulario', str_contains($p['cuerpo'], 'name="usuario"') && !str_contains($p['cuerpo'], 'Últimos cambios'));

    // 9b. Un cambio de rol surte efecto en la siguiente acción, sin volver a entrar.
    $r = pedir($base, 'POST', [], ['accion' => 'entrar', 'usuario' => 'mario', 'clave' => 'clave-de-mario-321']);
    $mario = $r['cookies'];
    $p = pedir($base, 'GET', $mario);
    $tokenMario = csrf($p['cuerpo']);
    comprobar('mario entra como editor', str_contains($p['cuerpo'], 'mario · editor'));
    $usr->cambiarRol('mario', 'lector');
    pedir($base, 'POST', $mario, ['accion' => 'cambiar', 'csrf' => $tokenMario, 'ref' => 'gen.mascotas:perritos', 'valor' => '0']);
    $p = pedir($base, 'GET', $mario);
    comprobar('bajar el rol surte efecto en la siguiente acción',
        str_contains($p['cuerpo'], 'mario · lector') && aviso($p['cuerpo']) === 'Tu rol (lector) no permite cambiar nada.');

    // 9c. Un usuario borrado queda fuera en su siguiente petición.
    $usr->borrar('mario');
    $p = pedir($base, 'GET', $mario);
    $p = pedir($base, 'GET', $p['cookies'] !== [] ? $p['cookies'] : $mario);
    comprobar('un usuario borrado queda fuera y se le avisa',
        aviso($p['cuerpo']) === 'Tu usuario ya no existe.' && str_contains($p['cuerpo'], 'name="usuario"'));

    // 10. Inactividad: pasado el tiempo, la sesión caduca. (INTERRUPTORES_CADUCIDAD=6 en el servidor.)
    $r = pedir($base, 'POST', [], ['accion' => 'entrar', 'usuario' => 'ana', 'clave' => 'clave-de-ana-123']);
    $ana2 = $r['cookies'];
    $p = pedir($base, 'GET', $ana2);
    comprobar('ana vuelve a entrar para probar la caducidad', str_contains($p['cuerpo'], 'ana · editor'));
    sleep(8);
    $p = pedir($base, 'GET', $ana2);
    $p = pedir($base, 'GET', $p['cookies'] !== [] ? $p['cookies'] : $ana2);
    comprobar('pasada la inactividad la sesión caduca y se avisa',
        aviso($p['cuerpo']) === 'Sesión caducada por inactividad. Entra de nuevo.');

    // 10b. El registro de accesos: IP enmascarada, cada evento, y ninguna clave.
    $log = (string) @file_get_contents($var . '/accesos.jsonl');
    comprobar('el registro guarda la IP enmascarada y no la completa',
        str_contains($log, '"origen":"127.0.0.0"') && !str_contains($log, '127.0.0.1'));
    foreach (['entrar', 'entrar_fallido', 'bloqueado', 'salir', 'caducada', 'usuario_borrado'] as $evento) {
        comprobar("el registro recoge «{$evento}»", str_contains($log, "\"evento\":\"{$evento}\""));
    }
    comprobar('el registro nunca guarda claves', !str_contains($log, 'clave-de-') && !str_contains($log, 'mala-clave-larga'));

    // 11. Sin usuarios, el panel no entra.
    rename($var . '/usuarios.json', $var . '/usuarios.json.aparte');
    $p = pedir($base);
    comprobar('sin usuarios, el panel responde 503 con la instrucción', $p['estado'] === 503 && str_contains($p['cuerpo'], 'bin/usuarios.php'));
}

// El servidor no tiene que haber registrado avisos ni errores de PHP, aunque la página se viera bien.
$registro = (string) @file_get_contents($var . '/servidor.log');
comprobar('el servidor no registra errores de PHP', preg_match('/PHP (Fatal|Warning|Notice|Deprecated)/', $registro) !== 1,
    trim(substr($registro, 0, 300)));

// --- Limpieza ---------------------------------------------------------------

if (is_resource($proc)) {
    proc_terminate($proc);
    proc_close($proc);
}
foreach (glob($var . '/*') ?: [] as $archivo) {
    @unlink($archivo);
}
@rmdir($var);

echo "$pasadas pruebas del panel pasaron, $fallos fallaron.\n";
exit($fallos === 0 ? 0 : 1);
