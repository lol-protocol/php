<?php

declare(strict_types=1);

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Cruce;
use ParsingFbInMas\Detector;
use ParsingFbInMas\Exclusiones;
use ParsingFbInMas\Json;
use ParsingFbInMas\Lote;
use ParsingFbInMas\Metadatos;
use ParsingFbInMas\Perfil;
use ParsingFbInMas\Revision;
use ParsingFbInMas\Sensibles;
use ParsingFbInMas\Wikidata;

/** Contadores de las comprobaciones (estáticos para que `igual()` no dependa de variables globales). */
final class Cuenta
{
    public static int $total = 0;
    public static int $fallos = 0;
}

function igual(string $que, mixed $esperado, mixed $real): void
{
    Cuenta::$total++;
    if ($esperado !== $real) {
        Cuenta::$fallos++;
        echo "FALLA: $que\n  esperado: " . var_export($esperado, true) . "\n  real:     " . var_export($real, true) . "\n";
    }
}

function muestra(string $nombre): string
{
    return (string) file_get_contents(__DIR__ . "/muestras/$nombre.html");
}

// Facebook
$f = Detector::analizar(muestra('facebook'), '2026-11-04')->aArreglo();
igual('facebook: red', 'facebook', $f['red']);
igual('facebook: nombre sin « | Facebook»', 'Ana Ejemplo', $f['nombre']);
igual('facebook: usuario', 'ana.ejemplo', $f['usuario']);
igual('facebook: descripción sin el contador de Me gusta', 'Genealogista aficionada & archivera.', $f['descripcion']);
igual('facebook: url', 'https://www.facebook.com/ana.ejemplo/', $f['url']);
igual('facebook: captura', '2026-11-04', $f['capturado']);
igual('facebook: sin avisos si trae todo', [], $f['avisos']);

$f = Detector::analizar(muestra('facebook-id'))->aArreglo();
igual('facebook profile.php: usuario por id', 'id:100000000000001', $f['usuario']);
igual('facebook: sin fecha de captura se avisa', true, in_array('sin fecha de captura: anótala al citar la fuente', $f['avisos'], true));

// LinkedIn
$l = Detector::analizar(muestra('linkedin'))->aArreglo();
igual('linkedin: red (subdominio cl.)', 'linkedin', $l['red']);
igual('linkedin: nombre', 'Carla Prueba', $l['nombre']);
igual('linkedin: titular', 'Archivista - Archivo Nacional', $l['titular']);
igual('linkedin: lugar', 'Santiago, Chile', $l['lugar']);
igual('linkedin: usuario', 'carla-prueba-0000', $l['usuario']);

// Twitter / X
$t = Detector::analizar(muestra('twitter'))->aArreglo();
igual('twitter: red (x.com)', 'twitter', $t['red']);
igual('twitter: nombre', 'Dana Ejemplo', $t['nombre']);
igual('twitter: usuario', 'danaejemplo', $t['usuario']);
igual('twitter: descripción', 'Historia familiar y registros parroquiales.', $t['descripcion']);

// Instagram
$i = Detector::analizar(muestra('instagram'))->aArreglo();
igual('instagram: red', 'instagram', $i['red']);
igual('instagram: nombre', 'Ana Ejemplo', $i['nombre']);
igual('instagram: usuario con punto', 'ana.ejemplo', $i['usuario']);
igual('instagram: solo la biografía', 'Fotos de archivos y abuelos.', $i['descripcion']);
igual('instagram: los contadores no salen', false, str_contains((string) json_encode($i), 'Followers'));

// Genérico (schema.org Person)
$g = Detector::analizar(muestra('generico'))->aArreglo();
igual('genérico: red', 'generico', $g['red']);
igual('genérico: nombre', 'Elena Ficticia', $g['nombre']);
igual('genérico: titular', 'Paleógrafa', $g['titular']);
igual('genérico: lugar', 'Valparaíso, CL', $g['lugar']);

// Privacidad: lo que no se debe llevar
$json = (string) json_encode($g, JSON_UNESCAPED_UNICODE);
igual('privacidad: el correo no sale', false, str_contains($json, 'elena@example.org'));
igual('privacidad: el teléfono no sale', false, str_contains($json, '+56 9'));
igual('privacidad: la fecha de nacimiento no sale', false, str_contains($json, '1980'));
$persona = Metadatos::leer(muestra('generico'))['persona'];
igual('privacidad: la lista blanca de Metadatos', ['nombre', 'titular', 'descripcion', 'lugar'], array_keys($persona ?? []));
foreach ([$f, $l, $t, $i, $g] as $perfil) {
    igual('todo perfil sale como posiblemente vivo', true, $perfil['posiblemente_vivo']);
    igual('todo perfil sale pendiente de revisión humana', true, $perfil['revision_humana']);
}

// Páginas sin datos
$v = Detector::analizar(muestra('vacia'))->aArreglo();
igual('página de inicio de sesión: sin nombre', null, $v['nombre']);
igual('página de inicio de sesión: avisa', true, str_starts_with($v['avisos'][0] ?? '', 'sin nombre'));
try {
    Detector::analizar(muestra('desconocida'));
    igual('página desconocida: lanza', true, false);
} catch (RuntimeException) {
    igual('página desconocida: lanza', true, true);
}

// Descripción larga
$largo = '<html><head><link rel="canonical" href="https://x.com/uno"><meta property="og:title" content="Uno (@uno) / X"><meta property="og:description" content="' . str_repeat('a', 900) . '"></head></html>';
igual('descripción recortada a 500', 500, mb_strlen(Detector::analizar($largo)->descripcion ?? ''));

// Entrada hostil: no revienta
foreach (['', '<<<', "\0\0", '<script type="application/ld+json">{no es json</script>'] as $i => $basura) {
    try {
        Detector::analizar($basura);
    } catch (RuntimeException) {
    }
    igual("entrada rara $i no revienta", true, true);
}

// --- Regresiones de la etapa 1 ---
// (1) Partículas y nombre de pila en el cruce
$fallecido = static fn (string $nombre, string $nac, ?string $lugar = null): array => [
    'red' => 'x', 'url' => 'u-' . $nombre, 'nombre' => $nombre, 'nacimiento' => $nac, 'muerte' => '1920',
    'lugar_nacimiento' => $lugar, 'posiblemente_vivo' => false,
];
igual('cruce: «María de la Cruz» no es «José de la Cruz»', 0,
    count(Cruce::candidatos([$fallecido('María de la Cruz', '1850')], [$fallecido('José de la Cruz', '1850')])['candidatos']));
igual('cruce: «María de la Cruz Soto» sí es «Maria Cruz Soto»', 1,
    count(Cruce::candidatos([$fallecido('María de la Cruz Soto', '1850')], [$fallecido('Maria Cruz Soto', '1850')])['candidatos']));
igual('cruce: el orden «Apellido, Nombre» no estorba', 1,
    count(Cruce::candidatos([$fallecido('González, María', '1850')], [$fallecido('María González', '1850')])['candidatos']));

// (2) El país solo no es evidencia de lugar
$solo = Cruce::candidatos([$fallecido('Ana Pérez', '1850', 'Chile')], [$fallecido('Ana Pérez Soto', '1850', 'Chile')])['candidatos'];
igual('cruce: «Chile» contra «Chile» no suma evidencia de lugar', false, str_contains(implode('|', $solo[0]['evidencia'] ?? []), 'lugar'));
$ciudad = Cruce::candidatos([$fallecido('Ana Pérez', '1850', 'Valparaíso, Chile')], [$fallecido('Ana Pérez Soto', '1850', 'Valparaiso, Chile')])['candidatos'];
igual('cruce: la misma ciudad sí suma', true, str_contains(implode('|', $ciudad[0]['evidencia'] ?? []), 'lugar'));
$q = Cruce::candidatos([$fallecido('Ana Pérez', '1850', 'Q2887')], [$fallecido('Ana Pérez Soto', '1850', 'Q2887')])['candidatos'];
igual('cruce: el mismo identificador de Wikidata suma', true, str_contains(implode('|', $q[0]['evidencia'] ?? []), 'lugar'));

// (3) Rutas que no son perfiles
$pagina = static fn (string $url, string $titulo): array => Detector::analizar(
    '<link rel="canonical" href="' . $url . '"><meta property="og:title" content="' . $titulo . '">'
)->aArreglo();
igual('facebook /people/: usuario por número', 'id:100000000000001', $pagina('https://www.facebook.com/people/Ana-Ejemplo/100000000000001/', 'Ana Ejemplo')['usuario']);
$home = $pagina('https://x.com/home', 'Ana Ejemplo');
igual('twitter /home: sin usuario', null, $home['usuario']);
igual('twitter /home: avisa que no es un perfil', true, in_array('la dirección parece una publicación u otra página, no un perfil: sin usuario', $home['avisos'], true));
igual('instagram /p/: sin usuario', null, $pagina('https://www.instagram.com/p/ABC123/', 'Ana Ejemplo')['usuario']);
igual('github /orgs/: sin usuario', null, $pagina('https://github.com/orgs/acme/people', 'Acme')['usuario']);
igual('instagram: un usuario normal sigue saliendo', 'ana.ejemplo', $pagina('https://www.instagram.com/ana.ejemplo/', 'Ana (@ana.ejemplo)')['usuario']);

// (4) Perfil hace cumplir la regla de las fechas
$sinMuerte = new Perfil('x', null, null, 'Fulano', null, null, null, null, [], '1985-05-05', null, 'Temuco', 'Santiago', [['relacion' => 'madre', 'nombre' => null, 'id' => 'Q2']]);
$a = $sinMuerte->aArreglo();
igual('perfil: sin muerte descarta el nacimiento', null, $a['nacimiento']);
igual('perfil: sin muerte descarta ambos lugares', [null, null], [$a['lugar_nacimiento'], $a['lugar_muerte']]);
igual('perfil: sin muerte descarta los parientes', [], $a['parientes']);
igual('perfil: sin muerte sigue como posiblemente vivo', true, $a['posiblemente_vivo']);
$conMuerte = new Perfil('x', null, null, 'Fulano', null, null, null, null, [], '1850', '1920', 'Temuco', 'Santiago', [['relacion' => 'madre', 'nombre' => null, 'id' => 'Q2']]);
igual('perfil: con muerte conserva todo', ['1850', 'Temuco', 1], [$conMuerte->nacimiento, $conMuerte->lugarNacimiento, count($conMuerte->parientes)]);

// --- Etapa 2: helpers compartidos ---
igual('esUrl: https', true, Lote::esUrl('https://www.facebook.com/ana'));
igual('esUrl: ftp', true, Lote::esUrl('ftp://x/y'));
igual('esUrl: una ruta', false, Lote::esUrl('muestras/facebook.html'));
igual('esUrl: una ruta de Windows', false, Lote::esUrl('C:\\datos\\a.html'));
igual('JSON: sin escapar tildes ni barras', 'María https://x/y', json_decode((string) json_encode('María https://x/y', Lote::JSON)));
igual('JSON: la salida literal conserva la tilde y la barra', '"María https://x/y"', json_encode('María https://x/y', Lote::JSON));
igual('tiktok: usuario desde la ruta si el título no lo trae', 'ana.ejemplo', Detector::analizar('<link rel="canonical" href="https://www.tiktok.com/@ana.ejemplo"><meta property="og:title" content="TikTok">')->usuario);
igual('youtube: una ruta sin @ no da usuario', null, Detector::analizar('<link rel="canonical" href="https://www.youtube.com/channel/UC123"><meta property="og:title" content="Canal">')->usuario);
igual('pinterest: sin paréntesis cae al usuario de la ruta', 'anaejemplo', Detector::analizar('<link rel="canonical" href="https://www.pinterest.com/anaejemplo/"><meta property="og:title" content="Ana Ejemplo | Pinterest">')->usuario);
igual('genérico: el titular también sale', 'Paleógrafa', Detector::analizar(muestra('generico'))->titular);

// --- Etapa 3: prefiltro de Wikidata ---
$cuenta = Wikidata::recorrer(__DIR__ . '/muestras/wikidata-muestra.json', static function (): void {
});
igual('wikidata: 3 líneas con muerte, 2 decodificadas (el gato no), 2 personas', [3, 2, 2], [$cuenta['leidas'], $cuenta['decodificadas'], $cuenta['aceptadas']]);
$cuentaPais = Wikidata::recorrer(__DIR__ . '/muestras/wikidata-muestra.json', static function (): void {
}, 'Q298');
igual('wikidata: con país solo se decodifica la chilena', [1, 1], [$cuentaPais['decodificadas'], $cuentaPais['aceptadas']]);
// Un volcado con espacios («"id": "Q5"») no puede perder personas en silencio
$espaciado = sys_get_temp_dir() . '/pfm-espaciado-' . getmypid() . '.json';
file_put_contents($espaciado, str_replace(['"id":"', '":"'], ['"id": "', '": "'], (string) file_get_contents(__DIR__ . '/muestras/wikidata-muestra.json')));
$vistas = [];
Wikidata::recorrer($espaciado, static function ($p) use (&$vistas): void {
    $vistas[] = $p->usuario;
}, 'Q298');
unlink($espaciado);
igual('wikidata: el prefiltro tolera JSON con espacios', ['Q1'], $vistas);
// Una línea con el identificador del país en otro lugar no cambia el resultado (el prefiltro solo descarta)
$otro = Wikidata::recorrer(__DIR__ . '/muestras/wikidata-muestra.json', static function (): void {
}, 'Q99999');
igual('wikidata: un país que nadie tiene no devuelve a nadie', [0, 0], [$otro['decodificadas'], $otro['aceptadas']]);

// --- Revisión del texto y datos sensibles ---
igual('sensibles: una biografía normal no marca nada', [], Sensibles::buscar('Genealogista aficionada y archivera.'));
igual('sensibles: salud y religión, con tilde y mayúscula', ['salud', 'religión'], Sensibles::buscar('Tengo CÁNCER y soy testigo de Jehová'));
igual('sensibles: política y origen étnico', ['política o sindical', 'origen étnico'], Sensibles::buscar('Militante del partido comunista, mapuche'));
igual('sensibles: palabras ambiguas no dan falsas alarmas', [], Sensibles::buscar('Barrio Iglesia Vieja, Roma, Italia'));
igual('sensibles: una raíz no marca a media palabra', [], Sensibles::buscar('Mi profesión es la tecnología'));
igual('revision: un RUT sale marcado', true, in_array('dato personal (RUT)', Revision::marcas('Mi RUT es 12.345.678-5'), true));
igual('revision: una acusación sale marcada', true, in_array('acusación de delito', Revision::marcas('Ese tipo es un ladrón y estuvo preso por robo'), true));
igual('revision: sin etiquetas repetidas', Revision::marcas('cáncer cáncer cáncer'), array_values(array_unique(Revision::marcas('cáncer cáncer cáncer'))));
$conRut = '<link rel="canonical" href="https://x.com/uno"><meta property="og:title" content="Uno (@uno) / X"><meta property="og:description" content="Mi RUT es 12.345.678-5 y tengo diabetes">';
$marcado = Detector::analizar($conRut)->aArreglo();
igual('perfil: las marcas salen en el perfil', true, in_array('dato personal (RUT)', $marcado['marcas'], true) && in_array('posible dato sensible: salud', $marcado['marcas'], true));
igual('perfil: avisa que hay que leer con cuidado', true, str_starts_with(implode('|', array_filter($marcado['avisos'], static fn (string $a): bool => str_starts_with($a, 'leer el texto'))), 'leer el texto con cuidado'));
igual('perfil: el texto no se borra ni se cambia', 'Mi RUT es 12.345.678-5 y tengo diabetes', $marcado['descripcion']);
igual('perfil: sin revisión, sin marcas', [], Detector::analizar($conRut, null, false)->aArreglo()['marcas']);
igual('perfil: una biografía limpia no tiene marcas', [], Detector::analizar(muestra('facebook'))->aArreglo()['marcas']);

// --- Lista de exclusión ---
$lista = Exclusiones::desdeTexto("# retiros decididos\n\nhttps://www.Facebook.com/ana.ejemplo/\ntwitter:DanaEjemplo\n  wikidata:Q1  \n");
igual('exclusiones: cuenta las entradas, sin comentarios ni líneas vacías', 3, $lista->cantidad());
igual('exclusiones: la misma página aunque cambie mayúscula, www, http y barra', true, $lista->contiene(['url' => 'http://facebook.com/ana.ejemplo', 'red' => 'facebook', 'usuario' => 'otro']));
igual('exclusiones: por red y usuario, sin importar mayúsculas', true, $lista->contiene(['url' => 'https://x.com/danaejemplo', 'red' => 'twitter', 'usuario' => 'danaejemplo']));
igual('exclusiones: por identificador de Wikidata', true, $lista->contiene(['url' => 'https://www.wikidata.org/wiki/Q1', 'red' => 'wikidata', 'usuario' => 'Q1']));
igual('exclusiones: otra persona no queda excluida', false, $lista->contiene(['url' => 'https://x.com/otra', 'red' => 'twitter', 'usuario' => 'otra']));
igual('exclusiones: la misma cuenta en otra red no queda excluida', false, $lista->contiene(['url' => 'https://www.instagram.com/danaejemplo', 'red' => 'instagram', 'usuario' => 'danaejemplo']));
igual('exclusiones: una lista vacía no excluye a nadie', false, Exclusiones::vacia()->contiene(['url' => 'https://x.com/uno', 'red' => 'twitter', 'usuario' => 'uno']));
$f = $lista->filtrar([['url' => 'https://x.com/danaejemplo', 'red' => 'twitter', 'usuario' => 'danaejemplo'], ['url' => 'https://x.com/otra', 'red' => 'twitter', 'usuario' => 'otra']]);
igual('exclusiones: filtrar deja a la otra y cuenta uno', [1, 1], [count($f['perfiles']), $f['excluidos']]);
try {
    Exclusiones::desdeTexto("https://x.com/a\nesto no es una entrada");
    igual('exclusiones: una línea mala lanza', true, false);
} catch (RuntimeException $e) {
    igual('exclusiones: una línea mala lanza y dice cuál', true, str_contains($e->getMessage(), 'línea 2'));
}

// --- Mastodon, Bluesky y Threads ---
$ma = Detector::analizar(muestra('mastodon'))->aArreglo();
igual('mastodon: se reconoce por el título, sin dominio fijo', 'mastodon', $ma['red']);
igual('mastodon: usuario con su instancia', 'ana@mastodon.example', $ma['usuario']);
igual('mastodon: nombre sin el «(@…)»', 'Ana Ejemplo', $ma['nombre']);
igual('mastodon: biografía', 'Archivera. Transcribo actas parroquiales.', $ma['descripcion']);
$bs = Detector::analizar(muestra('bluesky'))->aArreglo();
igual('bluesky: red, usuario y nombre', ['bluesky', 'ana.bsky.social', 'Ana Ejemplo'], [$bs['red'], $bs['usuario'], $bs['nombre']]);
igual('bluesky: sin «(@…)» en el título, el usuario sale de la ruta', 'ana.bsky.social', Detector::analizar('<link rel="canonical" href="https://bsky.app/profile/ana.bsky.social"><meta property="og:title" content="Ana Ejemplo">')->usuario);
$th = Detector::analizar(muestra('threads'))->aArreglo();
igual('threads: red, usuario y nombre', ['threads', 'ana.ejemplo', 'Ana Ejemplo'], [$th['red'], $th['usuario'], $th['nombre']]);
igual('mastodon no se queda con una página de Twitter', 'twitter', Detector::analizar(muestra('twitter'))->red);
igual('mastodon no se queda con una persona genérica', 'generico', Detector::analizar(muestra('generico'))->red);
igual('un título «(@usuario)» sin instancia no es Mastodon', 'generico', Detector::analizar('<meta property="og:title" content="Ana (@ana)"><script type="application/ld+json">{"@type":"Person","name":"Ana"}</script>')->red);

// --- Wikidata: nombres de lugares y parientes ---
$base = ['red' => 'wikidata', 'url' => 'https://www.wikidata.org/wiki/Q1', 'usuario' => 'Q1', 'nombre' => 'Fulana', 'lugar_nacimiento' => 'Q2887',
    'lugar_muerte' => 'Q99999', 'avisos' => [], 'parientes' => [
        ['relacion' => 'padre', 'nombre' => null, 'id' => 'Q2'],
        ['relacion' => 'hijo', 'nombre' => null, 'id' => 'Q3'],
        ['relacion' => 'cónyuge', 'nombre' => null, 'id' => 'Q7'],
        ['relacion' => 'madre', 'nombre' => null, 'id' => 'Q555'],
    ]];
$volcadoNombres = __DIR__ . '/muestras/wikidata-nombres-muestra.json';
$n = Wikidata::resolverNombres($volcadoNombres, [$base]);
$pn = $n['perfiles'][0];
/** @param array<mixed> $a */
$clave = static fn (array $a, string $k): mixed => array_key_exists($k, $a) ? $a[$k] : 'sin clave';
igual('nombres: el lugar sale en español', 'Santiago de Chile', $clave($pn, 'lugar_nacimiento_nombre'));
igual('nombres: un lugar que no está en el volcado queda sin nombre', null, $clave($pn, 'lugar_muerte_nombre'));
igual('nombres: el identificador del lugar se conserva', 'Q2887', $pn['lugar_nacimiento']);
$porId = [];
$parientesNombrados = Json::en($pn, 'parientes');
foreach (is_array($parientesNombrados) ? $parientesNombrados : [] as $par) {
    $porId[Json::texto($par, 'id') ?? ''] = Json::en($par, 'nombre');
}
igual('nombres: un pariente fallecido sí se nombra', 'Pedro Ficticio', $clave($porId, 'Q2'));
igual('nombres: un pariente que puede estar vivo NO se nombra', null, $clave($porId, 'Q3'));
igual('nombres: un pariente que no está en el volcado queda sin nombre', null, $clave($porId, 'Q555'));
$avisosNombres = Json::en($pn, 'avisos');
igual('nombres: avisa que hay parientes sin nombre', true, is_array($avisosNombres) && in_array('hay parientes sin nombre: pueden estar vivos o no estar en el volcado', $avisosNombres, true));
igual('nombres: cuenta lo resuelto (lugar, padre, hijo, cónyuge) y lo pendiente', [4, ['Q99999', 'Q555']], [$n['resueltos'], $n['pendientes']]);
$ex = Wikidata::resolverNombres($volcadoNombres, [$base], Exclusiones::desdeTexto("wikidata:Q7\n"));
$parientesEx = Json::en($ex['perfiles'][0], 'parientes');
igual('nombres: un pariente excluido se quita del perfil', ['Q2', 'Q3', 'Q555'], array_column(is_array($parientesEx) ? $parientesEx : [], 'id'));
igual('nombres: sin identificadores que pedir no se abre el volcado', [], Wikidata::resolverNombres(__DIR__ . '/no-existe.json', [['red' => 'wikidata', 'parientes' => []]])['pendientes']);
try {
    Wikidata::resolverNombres(__DIR__ . '/no-existe.json', [$base]);
    igual('nombres: volcado inexistente lanza', true, false);
} catch (RuntimeException) {
    igual('nombres: volcado inexistente lanza', true, true);
}

// CLI: no descarga de la red
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' https://www.facebook.com/alguien 2>&1', $salida, $codigo);
igual('CLI: rechaza una URL', 1, $codigo);
igual('CLI: explica que no descarga', true, str_contains(implode("\n", $salida), 'no descarga'));
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --capturado=2026-11-04 ' . escapeshellarg(__DIR__ . '/muestras/twitter.html'), $salida, $codigo);
igual('CLI: archivo bueno sale con 0', 0, $codigo);

// Redes nuevas
$y = Detector::analizar(muestra('youtube'))->aArreglo();
igual('youtube: usuario sin @', 'anaejemplo', $y['usuario']);
igual('youtube: nombre', 'Ana Ejemplo', $y['nombre']);
$k = Detector::analizar(muestra('tiktok'))->aArreglo();
igual('tiktok: usuario con punto', 'ana.ejemplo', $k['usuario']);
igual('tiktok: nombre sin «Official | TikTok»', 'Ana Ejemplo', $k['nombre']);
$gh = Detector::analizar(muestra('github'))->aArreglo();
igual('github: nombre entre paréntesis', 'Ana Ejemplo', $gh['nombre']);
igual('github: usuario', 'anaejemplo', $gh['usuario']);
igual('github: biografía sin la cola «has N repositories»', 'Transcribo actas con código.', $gh['descripcion']);
$pi = Detector::analizar(muestra('pinterest'))->aArreglo();
igual('pinterest: usuario', 'anaejemplo', $pi['usuario']);
igual('pinterest: nombre', 'Ana Ejemplo', $pi['nombre']);

// Fuentes de personas fallecidas (microdatos de schema.org)
$w = Detector::analizar(muestra('wikitree'))->aArreglo();
igual('wikitree: red', 'wikitree', $w['red']);
igual('wikitree: nombre (no el «name» del lugar anidado)', 'María González Ficticia', $w['nombre']);
igual('wikitree: identificador', 'Gonzalez-1234', $w['usuario']);
igual('wikitree: nacimiento', '1850-03-02', $w['nacimiento']);
igual('wikitree: muerte', '1920-07-15', $w['muerte']);
igual('wikitree: lugar de nacimiento anidado', 'Valparaíso, Chile', $w['lugar_nacimiento']);
igual('wikitree: lugar de muerte', 'Santiago, Chile', $w['lugar_muerte']);
igual('wikitree: fallecida ya no es «posiblemente viva»', false, $w['posiblemente_vivo']);
igual('wikitree: aun así pide revisión', true, $w['revision_humana']);
$fg = Detector::analizar(muestra('findagrave'))->aArreglo();
igual('findagrave: número de memorial', '99999', $fg['usuario']);
igual('findagrave: nacimiento solo con año', '1851', $fg['nacimiento']);
igual('findagrave: muerte', '1920-07-16', $fg['muerte']);

// Una persona viva con fechas en la página: no se guardan
$vv = Detector::analizar(muestra('vivo-con-fechas'))->aArreglo();
igual('vivo: sin nacimiento', null, $vv['nacimiento']);
igual('vivo: sin lugar de nacimiento', null, $vv['lugar_nacimiento']);
igual('vivo: sigue como posiblemente vivo', true, $vv['posiblemente_vivo']);

// Wikidata
/** @var list<array<string, mixed>> $leidas */
$leidas = [];
$r = Wikidata::recorrer(__DIR__ . '/muestras/wikidata-muestra.json', static function ($p) use (&$leidas): void {
    $leidas[] = $p->aArreglo();
}, null, null, '2026-11-04');
igual('wikidata: solo humanos con fecha de muerte', ['Q1', 'Q4'], array_column($leidas, 'usuario'));
igual('wikidata: nombre en español', 'María González Ficticia', $leidas[0]['nombre']);
igual('wikidata: nacimiento al día', '1850-03-02', $leidas[0]['nacimiento']);
igual('wikidata: muerte con precisión de mes', '1920-07', $leidas[0]['muerte']);
igual('wikidata: lugar como identificador', 'Q2887', $leidas[0]['lugar_nacimiento']);
igual('wikidata: parientes', [['relacion' => 'padre', 'nombre' => null, 'id' => 'Q2']], $leidas[0]['parientes']);
igual('wikidata: etiqueta en inglés si no hay español', 'Fulano Extranjero', $leidas[1]['nombre']);
igual('wikidata: precisión de año', '1900', $leidas[1]['muerte']);
igual('wikidata: fallecido', false, $leidas[0]['posiblemente_vivo']);
$chilenos = [];
Wikidata::recorrer(__DIR__ . '/muestras/wikidata-muestra.json', static function ($p) use (&$chilenos): void {
    $chilenos[] = $p->usuario;
}, 'Q298');
igual('wikidata: filtro por país', ['Q1'], $chilenos);
$unico = [];
Wikidata::recorrer(__DIR__ . '/muestras/wikidata-muestra.json', static function ($p) use (&$unico): void {
    $unico[] = $p->usuario;
}, null, 1);
igual('wikidata: tope de personas', ['Q1'], $unico);
try {
    Wikidata::recorrer(__DIR__ . '/no-existe.json', static function (): void {
    });
    igual('wikidata: archivo inexistente lanza', true, false);
} catch (RuntimeException) {
    igual('wikidata: archivo inexistente lanza', true, true);
}

// Lote
$archivos = Lote::archivos([__DIR__ . '/muestras']);
$esperados = glob(__DIR__ . '/muestras/*.html') ?: [];
sort($esperados, SORT_STRING);
igual('lote: la carpeta se expande exactamente a sus .html, en orden', $esperados, $archivos);
igual('lote: hay muestras que probar', true, $esperados !== []);
igual('lote: no incluye el volcado .json', false, in_array(__DIR__ . '/muestras/wikidata-muestra.json', $archivos, true));
$dup = Lote::sinRepetidos([
    ['url' => 'https://www.Facebook.com/ana/', 'n' => 1],
    ['url' => 'http://facebook.com/ana', 'n' => 2],
    ['url' => null, 'n' => 3],
    ['url' => null, 'n' => 4],
]);
igual('lote: misma URL aunque cambie mayúscula, www, http y barra', [1, 3, 4], array_column($dup['perfiles'], 'n'));
igual('lote: cuenta los repetidos', 1, $dup['repetidos']);

// Cruce entre fallecidos
$pw = Detector::analizar(muestra('wikitree'))->aArreglo();
$pf = Detector::analizar(muestra('findagrave'))->aArreglo();
$c = Cruce::candidatos([$pw], [$pf]);
igual('cruce: «González» y «Gonsales» son la misma persona candidata', 1, count($c['candidatos']));
igual('cruce: nunca es una fusión', true, str_contains($c['candidatos'][0]['estado'] ?? '', 'no es una fusión'));
$distinto = $pf;
$distinto['nacimiento'] = '1900';
igual('cruce: nacimientos a más de 5 años no son candidatos', 0, count(Cruce::candidatos([$pw], [$distinto])['candidatos']));
$vivoSocial = Detector::analizar(muestra('instagram'))->aArreglo();
$vivoSocial['nombre'] = 'María González Ficticia';
$c = Cruce::candidatos([$vivoSocial], [$pw]);
igual('cruce: un perfil que puede estar vivo no se cruza', 0, count($c['candidatos']));
igual('cruce: se cuenta el perfil omitido', 1, $c['excluidos_vivos']);

// bin/cruzar.php y bin/wikidata.php
$tmp = sys_get_temp_dir() . '/pfm-' . getmypid();
mkdir($tmp);
file_put_contents("$tmp/a.jsonl", json_encode($pw, JSON_UNESCAPED_UNICODE) . "\n");
file_put_contents("$tmp/b.jsonl", json_encode($pf, JSON_UNESCAPED_UNICODE) . "\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/cruzar.php') . ' ' . escapeshellarg("$tmp/a.jsonl") . ' ' . escapeshellarg("$tmp/b.jsonl") . ' 2>/dev/null', $salida, $codigo);
igual('cruzar.php: sale con 0 y una línea de candidato', [0, 1], [$codigo, count($salida)]);
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/wikidata.php') . ' ' . escapeshellarg(__DIR__ . '/muestras/wikidata-muestra.json') . ' --pais=Q298 2>/dev/null', $salida, $codigo);
igual('wikidata.php: una persona chilena', [0, 1], [$codigo, count($salida)]);
exec('php ' . escapeshellarg(__DIR__ . '/../bin/wikidata.php') . ' https://dumps.wikimedia.org/x.json 2>&1', $salida, $codigo);
igual('wikidata.php: rechaza una URL', 2, $codigo);
// analizar.php sobre carpetas: una buena (con una página repetida) sale con 0; con una página ilegible, con 1
mkdir("$tmp/buenas");
copy(__DIR__ . '/muestras/facebook.html', "$tmp/buenas/uno.html");
copy(__DIR__ . '/muestras/facebook.html', "$tmp/buenas/copia-del-uno.html");
copy(__DIR__ . '/muestras/twitter.html', "$tmp/buenas/dos.html");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --jsonl ' . escapeshellarg("$tmp/buenas") . ' 2>/dev/null', $salida, $codigo);
igual('analizar.php: carpeta buena sale con 0', 0, $codigo);
igual('analizar.php: la misma URL sale una vez (2 de 3 páginas)', 2, count($salida));
mkdir("$tmp/mala");
copy(__DIR__ . '/muestras/desconocida.html', "$tmp/mala/rara.html");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --jsonl ' . escapeshellarg("$tmp/mala") . ' 2>/dev/null', $salida, $codigo);
igual('analizar.php: una página que no se reconoce sale con 1', [1, 0], [$codigo, count($salida)]);
foreach (['buenas', 'mala'] as $carpeta) {
    array_map('unlink', glob("$tmp/$carpeta/*") ?: []);
    rmdir("$tmp/$carpeta");
}

// analizar.php, wikidata.php y cruzar.php respetan la lista de exclusión
file_put_contents("$tmp/excluir.txt", "# retirada\nhttps://www.facebook.com/ana.ejemplo\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --jsonl --excluir=' . escapeshellarg("$tmp/excluir.txt") . ' ' . escapeshellarg(__DIR__ . '/muestras/facebook.html') . ' ' . escapeshellarg(__DIR__ . '/muestras/twitter.html') . ' 2>/dev/null', $salida, $codigo);
igual('analizar.php --excluir: sale solo la que no está en la lista', [0, 1], [$codigo, count($salida)]);
igual('analizar.php --excluir: y es la de Twitter', true, str_contains($salida[0] ?? '', '"red":"twitter"'));
file_put_contents("$tmp/mala.txt", "no es una entrada\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --excluir=' . escapeshellarg("$tmp/mala.txt") . ' ' . escapeshellarg(__DIR__ . '/muestras/twitter.html') . ' 2>&1', $salida, $codigo);
igual('analizar.php --excluir: una lista mala sale con 2 y no escribe perfiles', [2, false], [$codigo, str_contains(implode('', $salida), '"red"')]);
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --sin-revision --jsonl ' . escapeshellarg(__DIR__ . '/muestras/facebook.html') . ' 2>/dev/null', $salida, $codigo);
igual('analizar.php --sin-revision: sale sin marcas', [0, true], [$codigo, str_contains($salida[0] ?? '', '"marcas":[]')]);
file_put_contents("$tmp/excluir-wd.txt", "wikidata:Q1\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/wikidata.php') . ' ' . escapeshellarg(__DIR__ . '/muestras/wikidata-muestra.json') . ' --excluir=' . escapeshellarg("$tmp/excluir-wd.txt") . ' 2>/dev/null', $salida, $codigo);
igual('wikidata.php --excluir: sale Q4 y no Q1', [0, 1, true], [$codigo, count($salida), str_contains($salida[0] ?? '', '"usuario":"Q4"')]);

file_put_contents("$tmp/wd.jsonl", json_encode($base, Lote::JSON) . "\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/wikidata-nombres.php') . ' ' . escapeshellarg($volcadoNombres) . ' ' . escapeshellarg("$tmp/wd.jsonl") . ' 2>/dev/null', $salida, $codigo);
igual('wikidata-nombres.php: sale con 0 y un perfil con el lugar nombrado', [0, 1, true], [$codigo, count($salida), str_contains($salida[0] ?? '', '"lugar_nacimiento_nombre":"Santiago de Chile"')]);
exec('php ' . escapeshellarg(__DIR__ . '/../bin/wikidata-nombres.php') . ' https://dumps.wikimedia.org/x.json ' . escapeshellarg("$tmp/wd.jsonl") . ' 2>&1', $salida, $codigo);
igual('wikidata-nombres.php: rechaza una URL', 2, $codigo);
file_put_contents("$tmp/excluir-cruce.txt", "wikitree:Gonzalez-1234\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/cruzar.php') . ' ' . escapeshellarg("$tmp/a.jsonl") . ' ' . escapeshellarg("$tmp/b.jsonl") . ' --excluir=' . escapeshellarg("$tmp/excluir-cruce.txt") . ' 2>/dev/null', $salida, $codigo);
igual('cruzar.php --excluir: la persona excluida no genera candidatos', [0, 0], [$codigo, count($salida)]);
array_map('unlink', glob("$tmp/*") ?: []);
rmdir($tmp);

echo Cuenta::$total . " comprobaciones, " . Cuenta::$fallos . " fallos\n";
exit(Cuenta::$fallos > 0 ? 1 : 0);
