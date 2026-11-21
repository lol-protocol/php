<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/api/ayudantes.php';
require_once __DIR__ . '/../../servidor-php/codigo/api/interruptores.php';
require_once __DIR__ . '/../../servidor-php/codigo/rutas.php';

// Las funciones de la API que se pueden apagar con los interruptores del repositorio (interruptores/):
// api_exigir_funcion() y el cableado de publico/index.php. Sin BACKOFFICE_INTERRUPTORES, la API es la de siempre.

ini_set('error_log', '/dev/null'); // api_exigir_funcion() anota en el log los interruptores ilegibles; aquí no estorba

$repo = realpath(__DIR__ . '/../../../interruptores');
assert_verdadero(is_string($repo) && is_file($repo . '/catalogo.json'), 'interruptores: la carpeta interruptores/ del repositorio está al lado');
$base = sys_get_temp_dir() . '/sistema-nuevo-interruptores-' . bin2hex(random_bytes(4));
mkdir($base, 0775, true);

/**
 * Una copia de interruptores/ (código y catálogo) con el estado que se pida; $catalogo puede cambiar el catálogo.
 *
 * @param array<string,bool> $estado
 */
$interruptores = function (string $nombre, array $estado, ?callable $catalogo = null) use ($repo, $base): string {
    $dir = "$base/$nombre";
    mkdir("$dir/src", 0775, true);
    mkdir("$dir/var", 0775, true);
    $crudo = (string) file_get_contents("$repo/catalogo.json");
    if ($catalogo !== null) {
        $datos = json_decode($crudo, true);
        $crudo = json_encode($catalogo($datos), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    file_put_contents("$dir/catalogo.json", $crudo);
    foreach (['Interruptores.php', 'Excepciones.php', 'RegistroJsonl.php'] as $archivo) {
        copy("$repo/src/$archivo", "$dir/src/$archivo");
    }
    file_put_contents("$dir/var/estado.json", json_encode($estado, JSON_THROW_ON_ERROR));
    return $dir;
};

/** @return array{ok:bool,estado:int,codigo:?string} */
$exigir = function (string $handler, ?string $carpeta) use (&$exigir): array {
    http_response_code(200);
    ob_start();
    $ok = api_exigir_funcion($handler, $carpeta);
    $crudo = (string) ob_get_clean();
    $cuerpo = json_decode($crudo === '' ? '{}' : $crudo, true);
    return ['ok' => $ok, 'estado' => http_response_code(), 'codigo' => is_array($cuerpo) ? ($cuerpo['codigo'] ?? null) : null];
};

// --- Sin interruptores configurados, todo está encendido ---------------------------------------------------------

putenv('BACKOFFICE_INTERRUPTORES'); // sin la variable
assert_igual(null, api_interruptores(), 'interruptores: sin BACKOFFICE_INTERRUPTORES no se lee nada');
assert_igual(['ok' => true, 'estado' => 200, 'codigo' => null], $exigir('api_kpis', null), 'interruptores: sin configurar, api_kpis se atiende');

// --- Con el estado de fábrica tampoco se apaga nada -------------------------------------------------------------

$fabrica = $interruptores('fabrica', []);
foreach (API_RUTAS as $patron => $entrada) {
    if (($entrada[1] ?? null) === 'sesion') {
        continue;
    }
    assert_igual(true, $exigir($entrada[0], $fabrica)['ok'], "interruptores: con el estado de fábrica, $patron se atiende");
}

// --- Una función apagada responde 404 `funcion_apagada` (política «ocultar», la de fábrica) ----------------------

$sinKpis = $interruptores('sin-kpis', ['gen.backoffice:kpis' => false]);
assert_igual(['ok' => false, 'estado' => 404, 'codigo' => 'funcion_apagada'], $exigir('api_kpis', $sinKpis), 'interruptores: KPIs apagado -> 404 funcion_apagada');
assert_igual(true, $exigir('api_alerts', $sinKpis)['ok'], 'interruptores: apagar los KPIs no toca las alertas');
assert_igual(true, $exigir('api_timeline', $sinKpis)['ok'], 'interruptores: ni la línea de tiempo');

$sinAlertas = $interruptores('sin-alertas', ['gen.backoffice:alertas' => false]);
foreach (['api_alerts', 'api_alertas_config'] as $handler) {
    assert_igual(404, $exigir($handler, $sinAlertas)['estado'], "interruptores: alertas apagadas -> $handler responde 404");
}

$sinFiltros = $interruptores('sin-filtros', ['gen.backoffice:filtros' => false]);
foreach (['api_filtros', 'api_filtros_delete'] as $handler) {
    assert_igual(404, $exigir($handler, $sinFiltros)['estado'], "interruptores: filtros apagados -> $handler responde 404");
}

$sinFlujo = $interruptores('sin-flujo', ['gen.backoffice:flujo' => false]);
assert_igual(404, $exigir('api_timeline', $sinFlujo)['estado'], 'interruptores: flujo apagado -> api_timeline responde 404');

$sinNotas = $interruptores('sin-notas', ['gen.bak-notas' => false]);
assert_igual(404, $exigir('api_notas', $sinNotas)['estado'], 'interruptores: notas apagadas -> api_notas responde 404');

// Apagar todo el backoffice corta también lo que no tiene interruptor propio.
$sinBackoffice = $interruptores('sin-backoffice', ['gen.backoffice' => false]);
foreach (['api_users', 'api_groups', 'api_action_types', 'api_kpis', 'api_timeline', 'api_notas'] as $handler) {
    assert_igual(404, $exigir($handler, $sinBackoffice)['estado'], "interruptores: backoffice apagado -> $handler responde 404");
}

// --- La política «no_disponible» responde 503 y lo dice ----------------------------------------------------------

$noDisponible = $interruptores('no-disponible', ['gen.backoffice:kpis' => false], static function (array $catalogo): array {
    foreach ($catalogo['funciones'] as &$f) {
        if ($f['id'] === 'gen.backoffice') {
            $f['al_apagar'] = 'no_disponible';
        }
    }
    return $catalogo;
});
assert_igual(['ok' => false, 'estado' => 503, 'codigo' => 'funcion_no_disponible'], $exigir('api_kpis', $noDisponible), 'interruptores: política no_disponible -> 503 funcion_no_disponible');

// --- Si están configurados pero no se pueden leer, se corta ---------------------------------------------------------

assert_igual(['ok' => false, 'estado' => 503, 'codigo' => 'interruptores_ilegibles'], $exigir('api_kpis', "$base/no-existe"), 'interruptores: una carpeta sin catálogo -> 503 interruptores_ilegibles');
$danado = $interruptores('danado', []);
file_put_contents("$danado/var/estado.json", '{no es json');
assert_igual(503, $exigir('api_kpis', $danado)['estado'], 'interruptores: un estado dañado -> 503, no se atiende como si estuviera encendido');

// --- La comparación con el universo no apaga una ruta: apaga una parte de la respuesta ---------------------------------

assert_igual(true, api_funcion_encendida('gen.backoffice:comparacion', null), 'interruptores: sin configurar, la comparación está encendida');
assert_igual(true, api_funcion_encendida('gen.backoffice:comparacion', $fabrica), 'interruptores: con el estado de fábrica, también');
$sinComparacion = $interruptores('sin-comparacion', ['gen.backoffice:comparacion' => false]);
assert_igual(false, api_funcion_encendida('gen.backoffice:comparacion', $sinComparacion), 'interruptores: apagada, la comparación se omite');
assert_igual(true, $exigir('api_timeline', $sinComparacion)['ok'], 'interruptores: apagar la comparación no corta la ruta de la línea de tiempo');
assert_igual(false, api_funcion_encendida('gen.backoffice:comparacion', "$base/no-existe"), 'interruptores: ilegibles, la comparación se omite');

require_once __DIR__ . '/../../servidor-php/codigo/Universo.php';
require_once __DIR__ . '/../../servidor-php/codigo/ClienteEstadisticas.php';
require_once __DIR__ . '/../../servidor-php/codigo/AlmacenAlertas.php';
require_once __DIR__ . '/../../servidor-php/codigo/api/linea-tiempo-cohortes.php';
$accion = ['type' => 'login', 'duration_ms' => 120, 'amount_usd' => 5.0, 'ip_country' => 'AR'];
// Sin servidor de estadísticas: si intentara compararse, stats_service_available sería false.
$resultado = api_timeline_con_cohortes([$accion], 'AR', new Universo(null, 0, 150, 'all', 'u1'), false);
assert_igual(true, $resultado['stats_service_available'], 'interruptores: sin comparación no se llama al servicio de estadísticas');
assert_igual([null, null, null, false], [$resultado['timeline'][0]['cohort'], $resultado['timeline'][0]['duration_delta_pct'], $resultado['timeline'][0]['amount_delta_pct'], $resultado['timeline'][0]['ip_mismatch']], 'interruptores: sin comparación, cohorte y deltas en null; la alerta de IP sigue');

// --- Coherencia con la tabla de rutas y con el catálogo -----------------------------------------------------------

$conSesion = [];
$sinSesion = [];
foreach (API_RUTAS as $entrada) {
    ($entrada[1] ?? null) === 'sesion' ? $conSesion[] = $entrada[0] : $sinSesion[] = $entrada[0];
}
foreach (array_keys(API_INTERRUPTORES) as $handler) {
    assert_verdadero(in_array($handler, $sinSesion, true), "interruptores: $handler de API_INTERRUPTORES es una función de la API con sesión");
}
$base_ = array_values(array_diff($sinSesion, array_keys(API_INTERRUPTORES)));
sort($base_);
assert_igual(['api_action_types', 'api_groups', 'api_users'], $base_, 'interruptores: las funciones sin interruptor propio son la base del backoffice; una función nueva exige decidir su interruptor en API_INTERRUPTORES');

$real = api_interruptores($repo);
assert_verdadero($real !== null, 'interruptores: la carpeta real se puede cargar');
foreach ([API_INTERRUPTOR_BACKOFFICE, ...array_values(API_INTERRUPTORES)] as $ref) {
    $existe = true;
    try {
        $real?->activo($ref);
    } catch (Throwable) {
        $existe = false;
    }
    assert_verdadero($existe, "interruptores: «$ref» existe en el catálogo de interruptores/");
}

// --- De extremo a extremo: publico/index.php con una sesión fabricada (sin base de datos) -------------------------------

$sesiones = "$base/sesiones";
mkdir($sesiones, 0775, true);
file_put_contents("$sesiones/sess_pruebainterruptores01", 'username|s:3:"ana";csrf_token|s:6:"abcdef";');
$router = "$base/router.php";
file_put_contents($router, "<?php\nini_set('session.save_path', (string) getenv('SESIONES_DIR'));\nrequire (string) getenv('INDEX_PHP');\n");
$vivo = $interruptores('vivo', []);

$servidor = levantar_servidor_php($router, [
    'BACKOFFICE_INTERRUPTORES' => $vivo,
    'SESIONES_DIR' => $sesiones,
    'INDEX_PHP' => (string) realpath(__DIR__ . '/../../servidor-php/publico/index.php'),
]);
try {
    /** @return array{estado:int,cuerpo:array<string,mixed>} */
    $pedir = function (string $ruta, bool $conSesion = true) use ($servidor): array {
        $contexto = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => $conSesion ? "Cookie: PHPSESSID=pruebainterruptores01\r\n" : '',
            'ignore_errors' => true,
            'timeout' => 15,
        ]]);
        $crudo = (string) @file_get_contents("http://127.0.0.1:{$servidor['puerto']}$ruta", false, $contexto);
        $estado = 0;
        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m) === 1) {
                $estado = (int) $m[1];
            }
        }
        $cuerpo = json_decode($crudo, true);
        return ['estado' => $estado, 'cuerpo' => is_array($cuerpo) ? $cuerpo : []];
    };
    $guardarEstado = static function (array $estado) use ($vivo): void {
        file_put_contents("$vivo/var/estado.json", json_encode($estado, JSON_THROW_ON_ERROR));
    };
    $cortadaPorInterruptor = static fn (array $r): bool => in_array($r['cuerpo']['codigo'] ?? null, ['funcion_apagada', 'funcion_no_disponible', 'interruptores_ilegibles'], true);

    $guardarEstado([]);
    $r = $pedir('/api/session');
    assert_igual([200, true], [$r['estado'], $r['cuerpo']['authenticated'] ?? null], 'interruptores (index.php): la sesión fabricada vale');
    $r = $pedir('/api/kpis');
    assert_verdadero(!$cortadaPorInterruptor($r), 'interruptores (index.php): con el estado de fábrica, /api/kpis llega a su función (sin base de datos falla ahí, no en el interruptor)');

    $guardarEstado(['gen.backoffice:kpis' => false]);
    $r = $pedir('/api/kpis');
    assert_igual([404, 'funcion_apagada'], [$r['estado'], $r['cuerpo']['codigo'] ?? null], 'interruptores (index.php): KPIs apagado -> 404 funcion_apagada');
    assert_verdadero(!$cortadaPorInterruptor($pedir('/api/alerts')), 'interruptores (index.php): las alertas siguen');

    $guardarEstado(['gen.backoffice' => false]);
    $r = $pedir('/api/users');
    assert_igual([404, 'funcion_apagada'], [$r['estado'], $r['cuerpo']['codigo'] ?? null], 'interruptores (index.php): backoffice apagado -> /api/users responde 404');
    $r = $pedir('/api/session');
    assert_igual([200, true], [$r['estado'], $r['cuerpo']['authenticated'] ?? null], 'interruptores (index.php): session sigue respondiendo con el backoffice apagado');

    $r = $pedir('/api/kpis', false);
    assert_igual([401, 'no_autenticado'], [$r['estado'], $r['cuerpo']['codigo'] ?? null], 'interruptores (index.php): sin sesión responde 401 antes que cualquier interruptor');

    file_put_contents("$vivo/var/estado.json", '{no es json');
    $r = $pedir('/api/kpis');
    assert_igual([503, 'interruptores_ilegibles'], [$r['estado'], $r['cuerpo']['codigo'] ?? null], 'interruptores (index.php): un estado dañado corta con 503');
} finally {
    detener_servidor_php($servidor);
}

// --- Limpieza -----------------------------------------------------------------------------------------------------

$borrar = function (string $ruta) use (&$borrar): void {
    if (is_file($ruta) || is_link($ruta)) {
        unlink($ruta);
        return;
    }
    foreach (scandir($ruta) ?: [] as $e) {
        if ($e !== '.' && $e !== '..') {
            $borrar("$ruta/$e");
        }
    }
    rmdir($ruta);
};
$borrar($base);
