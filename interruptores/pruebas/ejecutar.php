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

/** Apaga $id después de apagar, primero, todo lo que la necesita (de hoja a raíz). */
function apagarEnCascada(Interruptores $i, string $id): void
{
    foreach ($i->dependientesActivos($id) as $dep) {
        apagarEnCascada($i, $dep);
    }
    $i->cambiar($id, false);
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
comprobar('el catálogo trae funciones', count($funciones) > 0);
comprobar('hay funciones en las tres plataformas', count(array_unique(array_column($funciones, 'plataforma'))) === 3);

$carpetasInexistentes = [];
foreach ($funciones as $id => $f) {
    // Las funciones «fuera_de_master» viven en un PR abierto: su carpeta aún no está en el repo.
    if (empty($f['fuera_de_master']) && !is_dir($repo . '/' . $f['carpeta'])) {
        $carpetasInexistentes[] = "$id → {$f['carpeta']}";
    }
}
comprobar('cada carpeta del catálogo existe en el repo', $carpetasInexistentes === [], implode('; ', $carpetasInexistentes));

// Sin ciclos de dependencia: si los hay, nunca se podrían encender.
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
        $buscar($dep, [...$pila, $id]);
    }
};
foreach (array_keys($funciones) as $id) {
    $buscar($id, []);
}
comprobar('no hay ciclos de dependencia', !$ciclo);

// Valores por defecto.
comprobar('gen.urls arranca encendida', $i->activo('gen.urls') === true);
comprobar('pos.chat-libros arranca apagada', $i->activo('pos.chat-libros') === false);

// Id desconocido.
comprobar('activo() con id inexistente lanza UnknownInterruptor', lanza(fn () => $i->activo('no.existe'), UnknownInterruptor::class));

// --- Dependencias -----------------------------------------------------------

$i = $nuevo();
comprobar('no se apaga el filtro de textos mientras retiros lo necesita',
    lanza(fn () => $i->cambiar('gen.filtro-textos', false), DependenciaRota::class));
comprobar('tras el fallo el filtro sigue encendido', $i->activo('gen.filtro-textos') === true);
comprobar('dependientesActivos lista gen.retiros',
    in_array('gen.retiros', $i->dependientesActivos('gen.filtro-textos'), true));

// El filtro de textos tiene varios dependientes: hay que apagarlos todos antes.
comprobar('dependientesActivos lista también notas y el editor de Marcalibros',
    count($i->dependientesActivos('gen.filtro-textos')) === 4);
apagarEnCascada($i, 'gen.filtro-textos');
comprobar('con sus dependientes apagados, el filtro de textos sí se apaga', $i->activo('gen.filtro-textos') === false);

comprobar('no se enciende gen.bak-notas si el filtro de textos está apagado',
    lanza(fn () => $i->cambiar('gen.bak-notas', true), DependenciaRota::class));
comprobar('faltanParaEncender señala el filtro',
    in_array('gen.filtro-textos', $i->faltanParaEncender('gen.bak-notas'), true));

$i->cambiar('gen.filtro-textos', true);
comprobar('con el filtro encendido, notas vuelve a encenderse sin error',
    lanza(fn () => $i->cambiar('gen.bak-notas', true), DependenciaRota::class) === false);
// La cascada apagó también el editor, los propios y la app de Marcalibros: se vuelven a encender en orden.
foreach (['gen.retiros', 'pos.mb-v-editor', 'pos.mb-h-propios', 'pos.mb-h-app'] as $volver) {
    $i->cambiar($volver, true);
}

// Cadena: apagar el acceso al backoffice exige apagar antes todo lo que cuelga de él.
$i = $nuevo();
comprobar('no se apaga gen.bak-acceso con el flujo encendido',
    lanza(fn () => $i->cambiar('gen.bak-acceso', false), DependenciaRota::class));
$i->cambiar('gen.bak-flujo', false);
$i->cambiar('gen.bak-comparacion', false);
$i->cambiar('gen.bak-kpis', false);
$i->cambiar('gen.bak-alertas', false);
$i->cambiar('gen.bak-notas', false);
$i->cambiar('gen.bak-filtros', false);
$i->cambiar('gen.bak-idioma', false);
$i->cambiar('gen.bak-acceso', false);
comprobar('con todo el backoffice apagado, el acceso también se apaga', $i->activo('gen.bak-acceso') === false);

// Las dos apps de Marcalibros: la de la web necesita el módulo propio.
$i = $nuevo();
comprobar('no se apaga el módulo propio mientras la app lo necesita',
    lanza(fn () => $i->cambiar('pos.mb-h-propios', false), DependenciaRota::class));
apagarEnCascada($i, 'pos.mb-h-propios');
comprobar('en cascada se apagan la app y luego el módulo', $i->activo('pos.mb-h-app') === false && $i->activo('pos.mb-h-propios') === false);
$i->cambiar('pos.mb-h-propios', true);
$i->cambiar('pos.mb-h-app', true);

// --- Persistencia -----------------------------------------------------------

$i = $nuevo();
$i->cambiar('lg.paises', false);
$otro = $nuevo();
comprobar('el estado se guarda y otra instancia lo lee', $otro->activo('lg.paises') === false);
comprobar('el archivo de estado es JSON con booleanos',
    json_decode((string) file_get_contents($estado), true)['lg.paises'] === false);

// Un id que ya no existe en el catálogo se ignora al leer.
file_put_contents($estado, json_encode(['fantasma.id' => false, 'lg.paises' => true]));
$otro = $nuevo();
comprobar('ids que no están en el catálogo se ignoran', !array_key_exists('fantasma.id', $otro->estados()));
comprobar('el valor guardado gana sobre el de por defecto', $otro->activo('lg.paises') === true);

file_put_contents($estado, '{no es json');
comprobar('un estado dañado lanza error y no se pisa en silencio',
    lanza(fn () => $nuevo()->activo('gen.urls'), \RuntimeException::class));

// --- Catálogo roto ----------------------------------------------------------

$malo = $tmp . '/malo.json';
$base = ['plataformas' => ['genealogia' => 'A']];
$fn = function (string $id, array $extra = []): array {
    return array_merge(['id' => $id, 'plataforma' => 'genealogia', 'nombre' => $id, 'carpeta' => 'x', 'tipo' => 'producto', 'descripcion' => '', 'depende_de' => [], 'por_defecto' => true], $extra);
};

file_put_contents($malo, json_encode(array_merge($base, ['funciones' => [$fn('a'), $fn('a')]])));
comprobar('un id repetido impide cargar el catálogo',
    lanza(fn () => new Interruptores($malo, $estado), \RuntimeException::class));

file_put_contents($malo, json_encode(array_merge($base, ['funciones' => [$fn('a', ['depende_de' => ['b']])]])));
comprobar('una dependencia que no existe impide cargar el catálogo',
    lanza(fn () => new Interruptores($malo, $estado), \RuntimeException::class));

file_put_contents($malo, json_encode(array_merge($base, ['funciones' => [$fn('a', ['plataforma' => 'nope'])]])));
comprobar('una plataforma que no existe impide cargar el catálogo',
    lanza(fn () => new Interruptores($malo, $estado), \RuntimeException::class));

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
