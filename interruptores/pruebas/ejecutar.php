<?php

declare(strict_types=1);

// Pruebas de interruptores/. Sin framework: php pruebas/ejecutar.php

require_once __DIR__ . '/../src/Interruptores.php';

use LolProtocol\Interruptores\DependenciaRota;
use LolProtocol\Interruptores\Interruptores;
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
comprobar('hay 32 funciones de genealogía', count(array_filter($funciones, fn ($f) => $f['plataforma'] === 'genealogia')) === 32);
comprobar('hay 20 funciones de POS', count(array_filter($funciones, fn ($f) => $f['plataforma'] === 'pos')) === 20);
comprobar('hay 255 interruptores en total (52 funciones + 203 opciones)', $i->totalInterruptores() === 255);

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
comprobar('una plataforma inexistente impide cargar', $cargar([$fn('a', ['plataforma' => 'nope'])]));
comprobar('un catálogo bueno sí carga', !$cargar([$fn('a', ['opciones' => [['id' => 'x']], 'min_opciones' => 1]), $fn('b', ['depende_de' => ['a:x']])]));

// --- FUNCIONES.md -----------------------------------------------------------

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/bin/generar.php') . ' --comprobar 2>&1', $salida, $codigo);
comprobar('FUNCIONES.md está al día con catalogo.json', $codigo === 0, implode(' ', $salida));

// Limpieza de la carpeta temporal.
foreach (glob($tmp . '/*') ?: [] as $archivo) {
    @unlink($archivo);
}
@rmdir($tmp);

echo "$pasadas pruebas pasaron, $fallos fallaron.\n";
exit($fallos === 0 ? 0 : 1);
