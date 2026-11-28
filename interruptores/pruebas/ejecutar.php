<?php

declare(strict_types=1);

// Pruebas de interruptores/. Sin framework: php pruebas/ejecutar.php

require_once __DIR__ . '/../src/Interruptores.php';
require_once __DIR__ . '/../src/Usuarios.php';
require_once __DIR__ . '/../src/RegistroJsonl.php';
require_once __DIR__ . '/../src/Ip.php';
require_once __DIR__ . '/../src/Escenas.php';
require_once __DIR__ . '/../src/Programados.php';

use LolProtocol\Interruptores\DependenciaRota;
use LolProtocol\Interruptores\Interruptores;
use LolProtocol\Interruptores\Escenas;
use LolProtocol\Interruptores\Ip;
use LolProtocol\Interruptores\Programados;
use LolProtocol\Interruptores\RegistroJsonl;
use LolProtocol\Interruptores\Usuarios;
use LolProtocol\Interruptores\UnknownInterruptor;

$raiz = dirname(__DIR__);
$repo = dirname($raiz);
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

function lanza(callable $f, string $clase): bool
{
    try {
        $f();
    } catch (\Throwable $e) {
        return $e instanceof $clase;
    }
    return false;
}

/** Apaga $ref después de apagar, primero, todo lo que depende de ella. */
function apagarEnCascada(Interruptores $i, string $ref): void
{
    foreach ($i->dependientesActivos($ref) as $dep) {
        apagarEnCascada($i, $dep);
    }
    $i->cambiar($ref, false);
}

// Estado en una carpeta temporal, para no tocar var/ del repo.
$tmp = sys_get_temp_dir() . '/interruptores-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
$cat = $raiz . '/catalogo.json';
$estado = $tmp . '/estado.json';
$nuevo = fn (): Interruptores => new Interruptores($cat, $estado);

// --- Catálogo ---------------------------------------------------------------

$i = $nuevo();
$funciones = $i->catalogo();
comprobar('hay 41 funciones de genealogía', count(array_filter($funciones, fn ($f) => $f['plataforma'] === 'genealogia')) === 41);
comprobar('hay 21 funciones de POS', count(array_filter($funciones, fn ($f) => $f['plataforma'] === 'pos')) === 21);
comprobar('hay 377 interruptores en total (62 funciones + 315 opciones)', $i->totalInterruptores() === 377);

$carpetasInexistentes = [];
foreach ($funciones as $id => $f) {
    if ($f['origen'] !== 'repo') {
        continue; // fuera_del_repo y pr_abierto no se comprueban aquí
    }
    foreach ($f['carpetas'] as $c) {
        if (!is_dir($repo . '/' . $c)) {
            $carpetasInexistentes[] = "$id → $c";
        }
    }
}
comprobar('cada carpeta de una función de origen «repo» existe', $carpetasInexistentes === [], implode('; ', $carpetasInexistentes));

// Sin ciclos de dependencia entre funciones.
$ciclo = false;
$visto = [];
$buscar = function (string $id, array $pila) use (&$buscar, &$visto, &$ciclo, $funciones): void {
    if (in_array($id, $pila, true)) {
        $ciclo = true;
        return;
    }
    if (isset($visto[$id])) {
        return;
    }
    $visto[$id] = true;
    foreach ($funciones[$id]['depende_de'] as $dep) {
        $buscar(explode(':', $dep, 2)[0], [...$pila, $id]);
    }
};
foreach (array_keys($funciones) as $id) {
    $buscar($id, []);
}
comprobar('no hay ciclos de dependencia', !$ciclo);

// Valores por defecto.
comprobar('gen.vistas arranca encendida', $i->activo('gen.vistas') === true);
comprobar('gen.vistas:circular arranca encendida', $i->activo('gen.vistas:circular') === true);
comprobar('pos.chat-libros arranca apagada', $i->activo('pos.chat-libros') === false);
comprobar('pos.chat-libros:ia arranca apagada', $i->activo('pos.chat-libros:ia') === false);

// Refs que no existen.
comprobar('activo() con función inexistente lanza UnknownInterruptor', lanza(fn () => $i->activo('no.existe'), UnknownInterruptor::class));
comprobar('activo() con opción inexistente lanza UnknownInterruptor', lanza(fn () => $i->activo('gen.vistas:nope'), UnknownInterruptor::class));

// --- Opciones ---------------------------------------------------------------

$i = $nuevo();
$i->cambiar('gen.mascotas', false);
comprobar('una opción no se ve si su función está apagada', $i->activo('gen.mascotas:perritos') === false);
comprobar('pero conserva su propio interruptor encendido', $i->interruptores()['gen.mascotas:perritos'] === true);
$i->cambiar('gen.mascotas', true);
$i->cambiar('gen.mascotas:gatitos', false);
comprobar('apagar gatitos no apaga perritos', $i->activo('gen.mascotas:perritos') === true && $i->activo('gen.mascotas:gatitos') === false);

// Mínimo de opciones: medios de pago necesita al menos 1 encendido mientras la función esté encendida.
$i = $nuevo();
$i->cambiar('gen.pagos:transferencia', false);
$i->cambiar('gen.pagos:tarjeta', false);
comprobar('se puede dejar una sola forma de pago', $i->activo('gen.pagos:efectivo') === true);
comprobar('no se puede apagar la última forma de pago',
    lanza(fn () => $i->cambiar('gen.pagos:efectivo', false), DependenciaRota::class));
$i->cambiar('gen.pagos', false);
comprobar('con la función apagada, el mínimo deja de aplicar',
    lanza(fn () => $i->cambiar('gen.pagos:efectivo', false), DependenciaRota::class) === false);
$i->cambiar('gen.pagos:efectivo', false);
comprobar('no se puede encender la función sin ninguna opción encendida',
    lanza(fn () => $i->cambiar('gen.pagos', true), DependenciaRota::class));
$i->cambiar('gen.pagos:tarjeta', true);
$i->cambiar('gen.pagos', true);
comprobar('con una opción encendida, la función vuelve a encenderse', $i->activo('gen.pagos') === true);
$i->cambiar('gen.pagos:transferencia', true);
$i->cambiar('gen.pagos:efectivo', true);
$i->cambiar('gen.pagos:tarjeta', true);

// --- Dependencias -----------------------------------------------------------

$i = $nuevo();
comprobar('no se apaga el filtro de textos (opción) mientras retiros lo necesita',
    lanza(fn () => $i->cambiar('gen.filtro:textos', false), DependenciaRota::class));
comprobar('no se apaga la función gen.filtro entera mientras retiros la necesita',
    lanza(fn () => $i->cambiar('gen.filtro', false), DependenciaRota::class));
comprobar('dependientesActivos de la opción incluye gen.retiros',
    in_array('gen.retiros', $i->dependientesActivos('gen.filtro:textos'), true));

apagarEnCascada($i, 'gen.filtro:textos');
comprobar('en cascada se apaga el filtro de textos y sus dependientes',
    $i->activo('gen.retiros') === false && $i->activo('gen.bak-notas') === false
    && $i->activo('pos.mb-v-editor') === false && $i->activo('pos.mb-h-propios') === false);
comprobar('no se enciende gen.retiros con su filtro apagado',
    lanza(fn () => $i->cambiar('gen.retiros', true), DependenciaRota::class));
comprobar('faltanParaEncender señala la opción del filtro',
    in_array('gen.filtro:textos', $i->faltanParaEncender('gen.retiros'), true));

// Restaurar: encender en orden de dependencias.
$i->cambiar('gen.filtro:textos', true);
$i->cambiar('gen.retiros', true);
$i->cambiar('gen.backoffice', true);
$i->cambiar('gen.bak-notas', true);
$i->cambiar('pos.mb-h-propios', true);
$i->cambiar('pos.mb-h-app', true);
$i->cambiar('pos.mb-v-editor', true);
comprobar('restaurar en orden deja todo encendido', $i->activo('gen.bak-notas') && $i->activo('pos.mb-h-app') && $i->activo('gen.retiros'));

// Cadena de Marcalibros: la app necesita el módulo propio.
comprobar('no se apaga el módulo propio mientras la app lo necesita',
    lanza(fn () => $i->cambiar('pos.mb-h-propios', false), DependenciaRota::class));
apagarEnCascada($i, 'pos.mb-h-propios');
comprobar('en cascada se apagan la app y luego el módulo',
    $i->activo('pos.mb-h-app') === false && $i->activo('pos.mb-h-propios') === false);
$i->cambiar('pos.mb-h-propios', true);
$i->cambiar('pos.mb-h-app', true);

// Notas del backoffice: depende de la función y de una opción de otra.
comprobar('no se apaga gen.backoffice mientras las notas lo necesitan',
    lanza(fn () => $i->cambiar('gen.backoffice', false), DependenciaRota::class));

// --- Persistencia -----------------------------------------------------------

$i = $nuevo();
$i->cambiar('gen.estilo-iconos:gris', false);
$otro = $nuevo();
comprobar('el estado se guarda y otra instancia lo lee', $otro->activo('gen.estilo-iconos:gris') === false);
comprobar('el archivo de estado es JSON con booleanos',
    json_decode((string) file_get_contents($estado), true)['gen.estilo-iconos:gris'] === false);

// Refs que ya no existen se ignoran al leer.
file_put_contents($estado, json_encode(['fantasma' => false, 'gen.vistas:fantasma' => false, 'gen.estilo-iconos:gris' => true]));
$otro = $nuevo();
comprobar('refs que no están en el catálogo se ignoran', !array_key_exists('fantasma', $otro->interruptores()));
comprobar('el valor guardado gana sobre el de por defecto', $otro->activo('gen.estilo-iconos:gris') === true);

file_put_contents($estado, '{no es json');
comprobar('un estado dañado lanza error y no se pisa en silencio',
    lanza(fn () => $nuevo()->activo('gen.vistas'), \RuntimeException::class));

// --- Catálogo roto ----------------------------------------------------------

$malo = $tmp . '/malo.json';
$base = ['plataformas' => ['genealogia' => 'A']];
$fn = function (string $id, array $extra = []): array {
    return array_merge([
        'id' => $id, 'plataforma' => 'genealogia', 'nombre' => $id, 'tipo' => 'producto',
        'origen' => 'repo', 'carpetas' => [], 'descripcion' => '', 'depende_de' => [],
        'por_defecto' => true, 'opciones' => [],
    ], $extra);
};
$cargar = fn (array $funciones): bool => lanza(function () use ($malo, $estado, $base, $funciones) {
    file_put_contents($malo, json_encode(array_merge($base, ['funciones' => $funciones])));
    new Interruptores($malo, $estado);
}, \RuntimeException::class);

comprobar('un id de función repetido impide cargar', $cargar([$fn('a'), $fn('a')]));
comprobar('un id de función con «:» impide cargar', $cargar([$fn('a:b')]));
comprobar('una opción repetida impide cargar', $cargar([$fn('a', ['opciones' => [['id' => 'x'], ['id' => 'x']]])]));
comprobar('un mínimo mayor que el número de opciones impide cargar', $cargar([$fn('a', ['min_opciones' => 2, 'opciones' => [['id' => 'x']]])]));
comprobar('una dependencia a una función inexistente impide cargar', $cargar([$fn('a', ['depende_de' => ['b']])]));
comprobar('una dependencia a una opción inexistente impide cargar', $cargar([$fn('a', ['opciones' => [['id' => 'x']]]), $fn('b', ['depende_de' => ['a:y']])]));
comprobar('una dependencia a sí misma impide cargar', $cargar([$fn('a', ['depende_de' => ['a']])]));
comprobar('una dependencia de opción a algo que no existe impide cargar', $cargar([$fn('a', ['opciones' => [['id' => 'x', 'depende_de' => ['zz']]]])]));
comprobar('una opción que depende de sí misma impide cargar', $cargar([$fn('a', ['opciones' => [['id' => 'x', 'depende_de' => ['a:x']]]])]));
comprobar('una plataforma inexistente impide cargar', $cargar([$fn('a', ['plataforma' => 'nope'])]));
comprobar('un catálogo bueno sí carga', !$cargar([$fn('a', ['opciones' => [['id' => 'x']], 'min_opciones' => 1]), $fn('b', ['depende_de' => ['a:x']])]));

// --- FUNCIONES.md -----------------------------------------------------------

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/bin/generar.php') . ' --comprobar 2>&1', $salida, $codigo);
comprobar('FUNCIONES.md está al día con catalogo.json', $codigo === 0, implode(' ', $salida));

// --- Registro de cambios ----------------------------------------------------

// La prueba del estado dañado dejó un estado.json roto a propósito: se quita antes de seguir.
@unlink($estado);
$i = $nuevo();
$i->cambiar('gen.estilo-iconos:gris', false, 'ana');
$i->cambiar('gen.estilo-iconos:gris', true, 'luis');
$h = $i->historial(2);
comprobar('el historial trae el cambio más nuevo primero', $h[0]['quien'] === 'luis' && $h[0]['despues'] === true);
comprobar('cada entrada guarda quién, qué y el antes y el después',
    $h[1]['quien'] === 'ana' && $h[1]['ref'] === 'gen.estilo-iconos:gris' && $h[1]['antes'] === true && $h[1]['despues'] === false);
comprobar('historial(n) respeta el límite', count($i->historial(1)) === 1);

$i->cambiar('gen.estilo-iconos:gris', false, "ana\n\t");
comprobar('el nombre de quien cambia pierde los caracteres de control', $i->historial(1)[0]['quien'] === 'ana');

$antes = count($i->historial(100000));
comprobar('un cambio rechazado no se anota', lanza(fn () => $i->cambiar('no.existe', true, 'ana'), UnknownInterruptor::class)
    && count($i->historial(100000)) === $antes);

// --- Usuarios ---------------------------------------------------------------

$uf = $tmp . '/usuarios.json';
$ahora = 1000000;
$reloj = function () use (&$ahora): int {
    return $ahora;
};
$usr = new Usuarios($uf, $reloj);
comprobar('sin archivo no hay usuarios', !$usr->hay());
comprobar('un nombre con mayúsculas o espacios se rechaza',
    lanza(fn () => $usr->crear('A B', 'editor', 'clave-muy-larga-1'), \InvalidArgumentException::class));
comprobar('un rol desconocido se rechaza', lanza(fn () => $usr->crear('ana', 'admin', 'clave-muy-larga-1'), \InvalidArgumentException::class));
comprobar('una clave corta se rechaza', lanza(fn () => $usr->crear('ana', 'editor', 'corta'), \InvalidArgumentException::class));
comprobar('una clave de más de 72 bytes se rechaza', lanza(fn () => $usr->crear('ana', 'editor', str_repeat('x', 73)), \InvalidArgumentException::class));
$usr->crear('ana', 'editor', 'clave-de-ana-123');
$usr->crear('luis', 'lector', 'clave-de-luis-456');
comprobar('crear un usuario que ya existe se rechaza', lanza(fn () => $usr->crear('ana', 'lector', 'otra-clave-larga-1'), \RuntimeException::class));
comprobar('listar no muestra hashes', !str_contains(json_encode($usr->listar()), 'hash') && array_keys($usr->listar()) === ['ana', 'luis']);
comprobar('la clave no queda en claro en el archivo', !str_contains((string) file_get_contents($uf), 'clave-de-ana-123'));
comprobar('verificar con la clave buena devuelve nombre y rol', $usr->verificar('ana', 'clave-de-ana-123') === ['nombre' => 'ana', 'rol' => 'editor']);
comprobar('verificar con una clave mala devuelve null', $usr->verificar('ana', 'mala-clave-larga') === null);
comprobar('un usuario que no existe devuelve null', $usr->verificar('nadie', 'clave-de-ana-123') === null);

for ($k = 0; $k < Usuarios::MAX_FALLOS; $k++) {
    $usr->verificar('ana', 'mala-clave-larga');
}
comprobar('tras MAX_FALLOS claves malas seguidas, el usuario queda bloqueado', $usr->bloqueado('ana'));
comprobar('bloqueado, ni la clave buena entra', $usr->verificar('ana', 'clave-de-ana-123') === null);
$ahora += Usuarios::BLOQUEO_SEGUNDOS + 1;
comprobar('pasado el bloqueo, vuelve a entrar', !$usr->bloqueado('ana') && $usr->verificar('ana', 'clave-de-ana-123') !== null);

comprobar('no se puede bajar al único editor', lanza(fn () => $usr->cambiarRol('ana', 'lector'), \RuntimeException::class));
comprobar('no se puede borrar al único editor', lanza(fn () => $usr->borrar('ana'), \RuntimeException::class));
$usr->crear('eva', 'editor', 'clave-de-eva-789');
$usr->cambiarRol('ana', 'lector');
comprobar('con otro editor, ana puede pasar a lector', $usr->listar()['ana']['rol'] === 'lector');
comprobar('ahora eva es el único editor y no se puede borrar', lanza(fn () => $usr->borrar('eva'), \RuntimeException::class));
$usr->cambiarRol('ana', 'editor');
$usr->borrar('eva');
comprobar('con dos editores sí se puede borrar uno', !isset($usr->listar()['eva']));

$usr->cambiarClave('luis', 'nueva-clave-luis-000');
comprobar('cambiar la clave: la nueva entra y la vieja no',
    $usr->verificar('luis', 'nueva-clave-luis-000') !== null && $usr->verificar('luis', 'clave-de-luis-456') === null);

// --- Registro JSON Lines: rotación y lectura desde el final -------------------

$reg = new RegistroJsonl($tmp . '/rj.jsonl', 300); // límite pequeño para probar la rotación
for ($k = 1; $k <= 40; $k++) {
    $reg->anotar(['n' => $k, 'relleno' => str_repeat('x', 40)]);
}
$ult = $reg->ultimas(3);
comprobar('ultimas devuelve los últimos eventos, el más nuevo primero', ($ult[0]['n'] ?? 0) === 40 && ($ult[2]['n'] ?? 0) === 38);
comprobar('al pasar el límite el archivo se aparta', count(glob($tmp . '/rj-*.jsonl') ?: []) >= 1);
comprobar('el archivo actual no crece sin límite', filesize($tmp . '/rj.jsonl') < 300 + 200);

$pequeno = new RegistroJsonl($tmp . '/pequeno.jsonl');
$pequeno->anotar(['n' => 1]);
$pequeno->anotar(['n' => 2]);
comprobar('dos anotar seguidos en el mismo proceso se ven los dos', count($pequeno->ultimas(10)) === 2);

$grande = new RegistroJsonl($tmp . '/grande.jsonl', PHP_INT_MAX);
for ($k = 1; $k <= 3000; $k++) {
    $grande->anotar(['n' => $k, 'relleno' => str_repeat('y', 60)]);
}
$ult = $grande->ultimas(500);
comprobar('ultimas(500) sobre 3000 eventos (varios bloques) da 3000..2501 en orden',
    count($ult) === 500 && $ult[0]['n'] === 3000 && $ult[499]['n'] === 2501);
comprobar('ultimas(n) con n mayor que el archivo devuelve todo', count($grande->ultimas(10000)) === 3000);
comprobar('ultimas(0) no devuelve nada', $grande->ultimas(0) === []);

// --- Ip: lo que se guarda en el registro ------------------------------------

comprobar('IPv4 pierde el último octeto', Ip::enmascarar('203.0.113.87') === '203.0.113.0');
comprobar('IPv6 se queda con el prefijo /64', Ip::enmascarar('2001:db8:1:2:3:4:5:6') === '2001:db8:1:2::');
comprobar('lo que no es una IP se guarda como «desconocida»', Ip::enmascarar('no es ip') === 'desconocida');

// --- Bloqueo por usuario y origen, con ventana -------------------------------

$ahora2 = 2000000;
$reloj2 = function () use (&$ahora2): int {
    return $ahora2;
};
$uo = new Usuarios($tmp . '/uo.json', $reloj2);
$uo->crear('mara', 'editor', 'clave-de-mara-111');
$uo->crear('nora', 'editor', 'clave-de-nora-222');
for ($k = 0; $k < Usuarios::MAX_FALLOS; $k++) {
    $uo->verificar('mara', 'mala-clave-larga', '10.0.0.1');
}
comprobar('bloqueado desde el origen que falló', $uo->bloqueado('mara', '10.0.0.1'));
comprobar('otro origen sigue pudiendo entrar (no lo bloquea otro)', $uo->verificar('mara', 'clave-de-mara-111', '10.0.0.2') !== null);
comprobar('el origen bloqueado no entra ni con la clave buena', $uo->verificar('mara', 'clave-de-mara-111', '10.0.0.1') === null);

for ($k = 0; $k < Usuarios::MAX_FALLOS - 1; $k++) {
    $uo->verificar('nora', 'mala-clave-larga', '10.0.0.3');
}
$ahora2 += Usuarios::BLOQUEO_SEGUNDOS + 1;
$uo->verificar('nora', 'mala-clave-larga', '10.0.0.3');
comprobar('fallos fuera de la ventana no suman para el bloqueo', !$uo->bloqueado('nora', '10.0.0.3'));

comprobar('buscar devuelve el rol actual de un usuario', $uo->buscar('mara') === ['nombre' => 'mara', 'rol' => 'editor']);
comprobar('buscar de un usuario que ya no existe devuelve null', $uo->buscar('nadie') === null);

// --- Política al apagar -----------------------------------------------------

comprobar('una función sin política usa «ocultar»', $i->alApagar('gen.vistas') === 'ocultar');
comprobar('una opción usa la política de su función', $i->alApagar('gen.vistas:abanico') === 'ocultar');
file_put_contents($malo, json_encode(array_merge($base, ['funciones' => [$fn('a', ['al_apagar' => 'mal'])]])));
comprobar('una política al apagar desconocida impide cargar el catálogo',
    lanza(fn () => new Interruptores($malo, $estado), \RuntimeException::class));

// --- Privacidad: lo que busca, deduce, infiere o estima depende de ella; y las políticas al apagar --------------

$i = $nuevo();
$buscan = ['gen.fuentes-externas', 'gen.directorios', 'gen.busqueda', 'gen.prensa', 'gen.congresos', 'gen.deduccion', 'gen.inferencia', 'gen.estimacion', 'gen.busqueda-fuentes', 'gen.busqueda-paises'];
$dependientes = $i->dependientesActivos('gen.privacidad');
sort($buscan);
sort($dependientes);
comprobar('todo lo que busca, deduce, infiere o estima depende de gen.privacidad', $dependientes === $buscan, implode(',', $dependientes));
comprobar('no se apaga la protección de personas vivas mientras algo las busca',
    lanza(fn () => $i->cambiar('gen.privacidad', false), DependenciaRota::class));
apagarEnCascada($i, 'gen.privacidad');
comprobar('en cascada se apaga todo lo que las busca y luego la protección', !$i->activo('gen.privacidad') && !$i->activo('gen.prensa'));
comprobar('sin la protección, no se enciende una búsqueda',
    lanza(fn () => $i->cambiar('gen.prensa', true), DependenciaRota::class));

$conNoDisponible = ['gen.pagos', 'gen.pasarela', 'pos.pasarela', 'gen.retiros', 'gen.exportar'];
foreach ($conNoDisponible as $id) {
    comprobar("«{$id}» se muestra como no disponible al apagarse", $i->alApagar($id) === 'no_disponible');
}
comprobar('una opción de la pasarela hereda «no_disponible»', $i->alApagar('gen.pasarela:stripe') === 'no_disponible');
comprobar('perritos y gatitos son dos interruptores independientes', array_column($i->catalogo()['gen.mascotas']['opciones'], 'id') === ['perritos', 'gatitos']);
comprobar('ya no queda ninguna función supuesta: la persona confirmó las de búsqueda',
    array_filter($i->catalogo(), fn ($f) => $f['supuesto']) === []);

// --- Dependencias de una opción: la tarjeta depende de Stripe ----------------------------------------------------

$i = $nuevo();
comprobar('no se apaga Stripe mientras la tarjeta lo necesita',
    lanza(fn () => $i->cambiar('gen.pasarela:stripe', false), DependenciaRota::class));
comprobar('dependientesActivos de Stripe incluye la opción tarjeta', in_array('gen.pagos:tarjeta', $i->dependientesActivos('gen.pasarela:stripe'), true));
comprobar('apagar la función pasarela también la protege la tarjeta',
    lanza(fn () => $i->cambiar('gen.pasarela', false), DependenciaRota::class));
apagarEnCascada($i, 'gen.pasarela');
comprobar('en cascada se apaga la tarjeta y luego la pasarela', !$i->activo('gen.pagos:tarjeta') && !$i->activo('gen.pasarela'));
comprobar('las otras formas de pago siguen: transferencia no depende de Stripe', $i->activo('gen.pagos:transferencia') === true);
comprobar('sin Stripe no se enciende la tarjeta', lanza(fn () => $i->cambiar('gen.pagos:tarjeta', true), DependenciaRota::class));
comprobar('faltanParaEncender de la tarjeta señala la opción de Stripe', in_array('gen.pasarela:stripe', $i->faltanParaEncender('gen.pagos:tarjeta'), true));
$i->cambiar('gen.pasarela', true);
$i->cambiar('gen.pagos:tarjeta', true);
comprobar('con Stripe encendido, la tarjeta vuelve', $i->activo('gen.pagos:tarjeta') === true);


// --- bin/estado.php: el estado efectivo para quien no es PHP ------------------------------------------------------

$var = $tmp . '/estado-efectivo';
mkdir($var, 0775, true);
file_put_contents($var . '/estado.json', json_encode(['gen.vistas:abanico' => false, 'gen.mascotas' => false]));
$volcar = function (array $args) use ($raiz, $var): array {
    $orden = 'INTERRUPTORES_VAR=' . escapeshellarg($var) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/bin/estado.php');
    foreach ($args as $a) {
        $orden .= ' ' . escapeshellarg($a);
    }
    exec($orden . ' 2>&1', $lineas, $codigo);
    return [$codigo, implode("\n", $lineas)];
};
[$codigo, $texto] = $volcar([]);
$vol = json_decode($texto, true);
comprobar('estado.php imprime JSON con versión y un interruptor por referencia', $codigo === 0 && ($vol['version'] ?? 0) === 1 && count($vol['interruptores']) === $i->totalInterruptores());
comprobar('una opción apagada sale inactiva', $vol['interruptores']['gen.vistas:abanico']['activo'] === false);
comprobar('una opción de una función apagada sale inactiva aunque su interruptor esté encendido', $vol['interruptores']['gen.mascotas:perritos']['activo'] === false);
comprobar('lo no tocado sale activo, con la política de su función', $vol['interruptores']['gen.pagos']['activo'] === true && $vol['interruptores']['gen.pagos']['al_apagar'] === 'no_disponible');
[$codigo] = $volcar(['--salida=' . $var . '/efectivo.json']);
comprobar('--salida escribe el archivo y no deja temporales', $codigo === 0 && is_file($var . '/efectivo.json') && glob($var . '/*.tmp') === []);
comprobar('una opción desconocida da código 2', $volcar(['--x'])[0] === 2);

// --- Instantánea del estado (congelar) y rendimiento --------------------------------------------------------------

@unlink($estado);
$i = $nuevo();
$otroProceso = $nuevo(); // otra instancia hace de «otro proceso» que cambia el archivo
$i->congelar();
comprobar('congelado, se lee el estado de fábrica', $i->activo('gen.mascotas:gatitos') === true);
$otroProceso->cambiar('gen.mascotas:gatitos', false);
comprobar('congelado, un cambio de otro proceso no se ve', $i->activo('gen.mascotas:gatitos') === true);
$i->descongelar();
comprobar('al descongelar se ve', $i->activo('gen.mascotas:gatitos') === false);
$i->congelar();
$i->cambiar('gen.mascotas:perritos', false);
comprobar('cambiar() ve el estado real aunque estuviera congelado, y descongela',
    $i->activo('gen.mascotas:perritos') === false && $i->activo('gen.mascotas:gatitos') === false);
$otroProceso->cambiar('gen.mascotas:gatitos', true);
$otroProceso->cambiar('gen.mascotas:perritos', true);

// El panel calcula, para cada botón, lo que falta y lo que depende: con todos los interruptores debe ser rápido.
file_put_contents($estado, json_encode(['gen.mascotas:gatitos' => false]));
$rapido = $nuevo();
$rapido->congelar();
$inicio = microtime(true);
foreach (array_keys($rapido->interruptores()) as $ref) {
    $rapido->faltanParaEncender($ref);
    $rapido->dependientesActivos($ref);
}
$segundos = microtime(true) - $inicio;
comprobar('calcular las dependencias de todos los botones tarda menos de 3 s', $segundos < 3.0, sprintf('%.2f s', $segundos));

// --- aplicar(): varios cambios a la vez, validando el estado final ---------------------------------------------------

@unlink($estado);
$i = $nuevo();
$antes = $i->aplicar(['gen.mascotas:perritos' => false, 'gen.mascotas:gatitos' => false, 'gen.vistas:abanico' => true], 'ana', 'prueba');
comprobar('aplicar devuelve solo lo que cambió, con su valor anterior', $antes === ['gen.mascotas:perritos' => true, 'gen.mascotas:gatitos' => true]);
comprobar('aplicar guarda todo', !$i->activo('gen.mascotas:perritos') && !$i->activo('gen.mascotas:gatitos'));
$h = $i->historial(2);
comprobar('aplicar anota cada cambio con su motivo', count($h) === 2 && $h[0]['motivo'] === 'prueba' && $h[0]['quien'] === 'ana');
$n = count($i->historial(100000));
comprobar('aplicar sin cambios reales devuelve [] y no anota', $i->aplicar(['gen.mascotas:perritos' => false]) === [] && count($i->historial(100000)) === $n);
$i->aplicar(['gen.mascotas:perritos' => true, 'gen.mascotas:gatitos' => true]);

comprobar('aplicar no deja un estado final roto: nada se guarda',
    lanza(fn () => $i->aplicar(['gen.privacidad' => false]), DependenciaRota::class) && $i->activo('gen.privacidad') === true);
comprobar('aplicar valida el estado final, no cada paso: se apaga la cadena entera de una vez',
    count($i->aplicar(array_fill_keys(['gen.privacidad', ...$buscan], false))) === count($buscan) + 1 && !$i->activo('gen.privacidad'));
comprobar('aplicar respeta el mínimo de opciones',
    lanza(fn () => $i->aplicar(['gen.pagos:transferencia' => false, 'gen.pagos:tarjeta' => false, 'gen.pagos:efectivo' => false]), DependenciaRota::class));
comprobar('aplicar con una ref que no existe lanza UnknownInterruptor', lanza(fn () => $i->aplicar(['no.existe' => true]), UnknownInterruptor::class));
$i->aplicar(array_fill_keys(['gen.privacidad', ...$buscan], true));

// --- Escenas -------------------------------------------------------------------------------------------------------

@unlink($estado);
$ultima = $tmp . '/ultima-escena.json';
@unlink($ultima);
$i = $nuevo();
$esc = new Escenas($raiz . '/escenas.json', $i, $ultima);
comprobar('hay escenas, y cada una nombra solo refs y tipos que existen (si no, el constructor falla)', count($esc->escenas()) >= 4);

$antesEstado = $i->interruptores();
$cambiados = $esc->aplicar('mantenimiento', 'ana');
comprobar('mantenimiento apaga los pagos y las búsquedas externas', !$i->activo('gen.pagos') && !$i->activo('gen.prensa') && count($cambiados) > 5);
comprobar('mantenimiento deja un estado válido', $i->activo('gen.privacidad') === true);
$esc->deshacer('ana');
comprobar('deshacer restaura exactamente el estado de antes', $i->interruptores() === $antesEstado);
comprobar('deshacer sin nada que deshacer lanza error', lanza(fn () => $esc->deshacer('ana'), \RuntimeException::class));

$esc->aplicar('emergencia', 'ana');
comprobar('emergencia apaga lo de producto, interno, datos y visual', !$i->activo('gen.vistas') && !$i->activo('gen.backoffice') && !$i->activo('gen.pagos') && !$i->activo('pos.portadas'));
comprobar('emergencia mantiene la protección, el filtro y los retiros',
    $i->activo('gen.privacidad') && $i->activo('gen.filtro') && $i->activo('gen.retiros') && $i->activo('gen.filtro:textos'));
comprobar('emergencia pide confirmación', $esc->escenas()['emergencia']['confirmar'] === true);
$esc->deshacer('ana');
comprobar('deshacer la emergencia restaura todo', $i->interruptores() === $antesEstado);

$esc->aplicar('silencio', 'ana');
comprobar('silencio apaga los asistentes y el marketing', !$i->activo('gen.mascotas:perritos') && !$i->activo('gen.marketing'));
$esc->aplicar('campana', 'ana');
comprobar('campaña enciende lo que silencio apagó', $i->activo('gen.mascotas:perritos') && $i->activo('gen.marketing:correo'));
comprobar('una escena inexistente lanza UnknownInterruptor', lanza(fn () => $esc->aplicar('nada', 'ana'), UnknownInterruptor::class));
$esc->deshacer('ana'); // solo se recuerda la última escena: deshace la campaña
comprobar('solo se deshace la última escena', $esc->ultima() === null);

$roto = $tmp . '/escenas-rotas.json';
file_put_contents($roto, json_encode(['escenas' => [['id' => 'mala', 'apagar' => ['no.existe']]]]));
comprobar('una escena que nombra una ref inexistente impide cargar', lanza(fn () => new Escenas($roto, $i, $ultima), \RuntimeException::class));
file_put_contents($roto, json_encode(['escenas' => [['id' => 'mala', 'apagar_tipos' => ['inventado']]]]));
comprobar('una escena con un tipo inexistente impide cargar', lanza(fn () => new Escenas($roto, $i, $ultima), \RuntimeException::class));

// --- Cambios programados -------------------------------------------------------------------------------------------

@unlink($estado);
$ahoraP = 3000000;
$relojP = function () use (&$ahoraP): int {
    return $ahoraP;
};
$i = $nuevo();
$esc = new Escenas($raiz . '/escenas.json', $i, $ultima);
$prog = new Programados($tmp . '/programados.json', $relojP);
comprobar('no se programa en el pasado', lanza(fn () => $prog->agendar($ahoraP - 1, ['escena' => 'silencio'], 'ana'), \InvalidArgumentException::class));
comprobar('no se programa con más de un año de adelanto', lanza(fn () => $prog->agendar($ahoraP + Programados::MAX_ADELANTO_SEGUNDOS + 1, ['escena' => 'silencio'], 'ana'), \InvalidArgumentException::class));
comprobar('hay que dar una escena o una ref con su valor', lanza(fn () => $prog->agendar($ahoraP + 60, ['x' => 1], 'ana'), \InvalidArgumentException::class));
$idEscena = $prog->agendar($ahoraP + 60, ['escena' => 'silencio'], 'ana');
$idRef = $prog->agendar($ahoraP + 120, ['ref' => 'gen.pagos', 'valor' => false], 'ana');
$idMalo = $prog->agendar($ahoraP + 180, ['ref' => 'gen.privacidad', 'valor' => false], 'ana');
$idCancelado = $prog->agendar($ahoraP + 240, ['ref' => 'gen.exportar', 'valor' => false], 'ana');
$prog->cancelar($idCancelado);
$listaP = $prog->listar();
comprobar('listar trae los pendientes primero', $listaP[0]['estado'] === 'pendiente' && end($listaP)['estado'] === 'cancelado');
comprobar('antes de la hora no se ejecuta nada', $prog->ejecutarVencidos($i, $esc) === [] && $i->activo('gen.pagos'));
$ahoraP += 125;
$hechos = $prog->ejecutarVencidos($i, $esc);
comprobar('pasada la hora se ejecutan los vencidos, en orden', array_column($hechos, 'id') === [$idEscena, $idRef] && !$i->activo('gen.pagos') && !$i->activo('gen.marketing'));
comprobar('lo ejecutado queda anotado como programado', $i->historial(1)[0]['quien'] === 'programado:ana');
comprobar('lo ya ejecutado no se repite', $prog->ejecutarVencidos($i, $esc) === []);
$ahoraP += 60;
$hechos = $prog->ejecutarVencidos($i, $esc);
comprobar('un cambio que ya no es válido queda en error, con el motivo, y no cambia nada',
    count($hechos) === 1 && $hechos[0]['estado'] === 'error' && str_contains($hechos[0]['resultado'], 'necesita') && $i->activo('gen.privacidad'));
comprobar('un cancelado no se ejecuta ni se puede cancelar de nuevo', $i->activo('gen.exportar') && lanza(fn () => $prog->cancelar($idCancelado), \RuntimeException::class));
comprobar('cancelar un cambio que no existe lanza error', lanza(fn () => $prog->cancelar('zzzz'), \RuntimeException::class));
comprobar('lo ya hecho no se cancela', lanza(fn () => $prog->cancelar($idEscena), \RuntimeException::class));

// Limpieza de la carpeta temporal.
foreach (glob($tmp . '/*') ?: [] as $archivo) {
    @unlink($archivo);
}
@rmdir($tmp);

echo "$pasadas pruebas pasaron, $fallos fallaron.\n";
exit($fallos === 0 ? 0 : 1);
