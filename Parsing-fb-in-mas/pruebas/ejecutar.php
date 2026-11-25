<?php

declare(strict_types=1);

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Cruce;
use ParsingFbInMas\Detector;
use ParsingFbInMas\Diagnostico;
use ParsingFbInMas\Exclusiones;
use ParsingFbInMas\Json;
use ParsingFbInMas\Lote;
use ParsingFbInMas\Metadatos;
use ParsingFbInMas\OpcionesCruce;
use ParsingFbInMas\Perfil;
use ParsingFbInMas\Purga;
use ParsingFbInMas\Revision;
use ParsingFbInMas\Sensibles;
use ParsingFbInMas\Wikidata;
use ParsingFbInMas\WikiTreeApi;

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
// El reparto que documenta el README: filtro normal (de un tercero), modo biografía (de uno mismo, spa y eng)
// y Sensibles (lo que lo anterior no ve), sin repetir la misma categoría.
$tiene = static fn (array $marcas, string $marca): bool => in_array($marca, $marcas, true);
$deTercero = Revision::marcas('Fulano tiene cáncer');
igual('revision: lo dicho de un tercero lo marca el filtro en su modo normal', [true, false], [$tiene($deTercero, 'dato sensible (salud)'), $tiene($deTercero, 'posible dato sensible: salud')]);
$deUnoMismo = Revision::marcas('Tengo cáncer');
igual('revision: lo dicho de uno mismo lo marca el modo biografía y Sensibles no lo repite', [true, false], [$tiene($deUnoMismo, 'dato sensible (salud)'), $tiene($deUnoMismo, 'posible dato sensible: salud')]);
igual('revision: religión de un tercero, por el filtro normal', true, $tiene(Revision::marcas('la familia era judía'), 'dato sensible (religión)'));
// Cada categoría de Sensibles tiene su etiqueta del filtro: si no, saldrían duplicadas.
foreach ([
    ['salud', 'Tengo diabetes', 'dato sensible (salud)'],
    ['religión', 'Soy evangélica', 'dato sensible (religión)'],
    ['política o sindical', 'Soy comunista', 'dato sensible (opinión política)'],
    ['vida sexual', 'Soy gay', 'dato sensible (orientación sexual)'],
    ['origen étnico', 'Soy mapuche', 'dato sensible (origen étnico)'],
    ['salud (inglés)', 'I have cancer', 'sensitive data (health)'],
] as [$categoria, $texto, $etiqueta]) {
    $m = Revision::marcas($texto);
    igual("revision: $categoria, «{$texto}»: la marca el filtro y Sensibles no la duplica", [true, 1], [$tiene($m, $etiqueta), count($m)]);
}
// Lo que el filtro no ve, lo cubre Sensibles: otros idiomas y biografías sin verbo.
igual('revision: otros idiomas los cubre Sensibles', ['posible dato sensible: salud', 'posible dato sensible: religión'], Revision::marcas('Je suis catholique et j\'ai un cancer'));
igual('revision: una biografía sin verbo la cubre Sensibles', ['posible dato sensible: origen étnico'], Revision::marcas('Mapuche. Archivera y genealogista'));
igual('revision: «Militante comunista» sin verbo la cubre Sensibles', ['posible dato sensible: política o sindical'], Revision::marcas('Militante comunista, historiadora'));
igual('revision: una biografía limpia no marca nada', [], Revision::marcas('Genealogista aficionada y archivera.'));
igual('revision: un RUT sale marcado', true, in_array('dato personal (RUT)', Revision::marcas('Mi RUT es 12.345.678-5'), true));
igual('revision: una acusación sale marcada', true, in_array('acusación de delito', Revision::marcas('Ese tipo es un ladrón y estuvo preso por robo'), true));
igual('revision: sin etiquetas repetidas', Revision::marcas('cáncer cáncer cáncer'), array_values(array_unique(Revision::marcas('cáncer cáncer cáncer'))));
$conRut = '<link rel="canonical" href="https://x.com/uno"><meta property="og:title" content="Uno (@uno) / X"><meta property="og:description" content="Mi RUT es 12.345.678-5 y tengo diabetes">';
$marcado = Detector::analizar($conRut)->aArreglo();
igual('perfil: las marcas salen en el perfil', true, in_array('dato personal (RUT)', $marcado['marcas'], true) && in_array('dato sensible (salud)', $marcado['marcas'], true));
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

// --- WikiTree por su API (respuesta JSON guardada) ---
$wt = array_map(static fn (Perfil $p): array => $p->aArreglo(), WikiTreeApi::desdeJson((string) file_get_contents(__DIR__ . '/muestras/wikitree-api.json'), '2026-11-11'));
igual('wikitree api: tres perfiles', 3, count($wt));
igual('wikitree api: nombre completo, usuario y url', ['María Rosa Ficticia', 'Ficticia-1', 'https://www.wikitree.com/wiki/Ficticia-1'], [$wt[0]['nombre'], $wt[0]['usuario'], $wt[0]['url']]);
igual('wikitree api: fecha de nacimiento con mes y sin día', '1850-03', $wt[0]['nacimiento']);
igual('wikitree api: fecha de muerte solo con año', '1920', $wt[0]['muerte']);
igual('wikitree api: lugares', ['Valparaíso, Chile', 'Santiago, Chile'], [$wt[0]['lugar_nacimiento'], $wt[0]['lugar_muerte']]);
igual('wikitree api: solo el padre que existe (la madre es 0)', [['relacion' => 'padre', 'nombre' => null, 'id' => '1002']], $wt[0]['parientes']);
igual('wikitree api: fallecida', false, $wt[0]['posiblemente_vivo']);
igual('wikitree api: una persona viva no filtra fecha, lugar ni parientes', [null, null, [], true], [$wt[1]['nacimiento'], $wt[1]['lugar_nacimiento'], $wt[1]['parientes'], $wt[1]['posiblemente_vivo']]);
igual('wikitree api: muerte desconocida («0000-00-00») no cuenta como fallecida y no filtra el nacimiento', [null, null, true], [$wt[2]['muerte'], $wt[2]['nacimiento'], $wt[2]['posiblemente_vivo']]);
igual('wikitree api: un perfil privado avisa que no tiene nombre', true, str_starts_with($wt[2]['avisos'][0] ?? '', 'sin nombre'));
igual('wikitree api: un objeto suelto con «profile» también sirve', 1, count(WikiTreeApi::desdeJson('{"profile":{"Name":"Uno-1","FirstName":"Uno"}}')));
igual('wikitree api: una lista de perfiles sueltos también sirve', 2, count(WikiTreeApi::desdeJson('[{"Name":"A-1"},{"Name":"B-2"}]')));
foreach (['no es json', '[]', '[{"status":"Permission denied"}]', '"texto"'] as $malo) {
    try {
        WikiTreeApi::desdeJson($malo);
        igual("wikitree api: «{$malo}» lanza", true, false);
    } catch (RuntimeException) {
        igual("wikitree api: «{$malo}» lanza", true, true);
    }
}

// --- Purga de lo ya generado (punto 4) ---
$excluirPurga = Exclusiones::desdeTexto("twitter:danaejemplo\nwikidata:Q2\n");
$quitar = Purga::linea((string) json_encode(['red' => 'twitter', 'usuario' => 'DanaEjemplo', 'url' => 'https://x.com/danaejemplo'], Lote::JSON), $excluirPurga);
igual('purga: la persona de la lista se quita', [null, false], [$quitar['linea'], $quitar['ilegible']]);
$otra = '{"red":"twitter","usuario":"otra","url":"https://x.com/otra"}';
igual('purga: otra persona se conserva tal cual, byte a byte', [$otra, 0], [Purga::linea($otra, $excluirPurga)['linea'], Purga::linea($otra, $excluirPurga)['parientes']]);
$conParientes = '{"red":"wikidata","usuario":"Q1","url":"https://www.wikidata.org/wiki/Q1","parientes":[{"relacion":"padre","nombre":null,"id":"Q2"},{"relacion":"madre","nombre":null,"id":"Q8"}]}';
$rp = Purga::linea($conParientes, $excluirPurga);
$parientesQueQuedan = Json::en(json_decode((string) $rp['linea'], true), 'parientes');
igual('purga: de un perfil que se queda se quita el pariente excluido', [1, ['Q8']], [$rp['parientes'], array_column(is_array($parientesQueQuedan) ? $parientesQueQuedan : [], 'id')]);
igual('purga: una línea que no es JSON se conserva y se marca ilegible', [true, 'esto no es json'], [Purga::linea('esto no es json', $excluirPurga)['ilegible'], Purga::linea('esto no es json', $excluirPurga)['linea']]);
igual('purga: un arreglo JSON tampoco es un perfil', true, Purga::linea('[1,2,3]', $excluirPurga)['ilegible']);

// --- Wikidata endurecido (punto 8) ---
$muestraWd = (string) file_get_contents(__DIR__ . '/muestras/wikidata-muestra.json');
$gz = sys_get_temp_dir() . '/pfm-wd-' . getmypid() . '.json.gz';
file_put_contents($gz, (string) gzencode($muestraWd));
$deGz = [];
$rGz = Wikidata::recorrer($gz, static function (Perfil $p) use (&$deGz): void {
    $deGz[] = $p->usuario;
});
unlink($gz);
igual('wikidata: un .json.gz da las mismas personas que el texto plano', ['Q1', 'Q4'], $deGz);
igual('wikidata: cuenta todas las líneas leídas (6: los corchetes y 4 entidades)', 6, $rGz['lineas']);
$conRota = sys_get_temp_dir() . '/pfm-wd-rota-' . getmypid() . '.json';
file_put_contents($conRota, str_replace("\n]", "\n,{\"id\":\"Q9\",\"P570\":{\"id\":\"Q5\"\n]", $muestraWd));
$deRota = [];
$rRota = Wikidata::recorrer($conRota, static function (Perfil $p) use (&$deRota): void {
    $deRota[] = $p->usuario;
});
unlink($conRota);
igual('wikidata: una línea corrupta se salta y no detiene la corrida', [['Q1', 'Q4'], 1], [$deRota, $rRota['corruptas']]);
$avisos = 0;
Wikidata::recorrer(__DIR__ . '/muestras/wikidata-muestra.json', static function (): void {
}, null, null, null, static function (int $lineas, int $aceptadas) use (&$avisos): void {
    $avisos++;
}, 2);
igual('wikidata: el progreso se avisa cada N líneas (6 líneas, cada 2: 3 avisos)', 3, $avisos);
$bz = sys_get_temp_dir() . '/pfm-wd-' . getmypid() . '.json.bz2';
file_put_contents($bz, 'x');
if (function_exists('bzopen')) {
    igual('wikidata: .bz2 con la extensión instalada no falla por falta de extensión', true, true);
} else {
    try {
        Wikidata::recorrer($bz, static function (): void {
        });
        igual('wikidata: .bz2 sin la extensión lanza', true, false);
    } catch (RuntimeException $e) {
        igual('wikidata: .bz2 sin la extensión lanza y dice qué falta', true, str_contains($e->getMessage(), 'bz2'));
    }
}
unlink($bz);

// --- Cruce: opciones, parientes y generadores (puntos 2 y 3) ---
$ficha = static fn (string $nombre, string $nac, ?string $lugar = null, array $parientes = []): array => [
    'red' => 'x', 'url' => 'u-' . $nombre, 'nombre' => $nombre, 'nacimiento' => $nac, 'muerte' => '1920',
    'lugar_nacimiento' => $lugar, 'parientes' => $parientes, 'posiblemente_vivo' => false,
];
$pa = $ficha('Ana Pérez Soto', '1850');
$pb = $ficha('Ana Pérez Soto', '1853');
igual('opciones: por defecto, 3 años de diferencia sí es candidata', 1, count(Cruce::candidatos([$pa], [$pb])['candidatos']));
igual('opciones: --anios=2 la descarta', 0, count(Cruce::candidatos([$pa], [$pb], 'spa', new OpcionesCruce(0.6, 2))['candidatos']));
igual('opciones: tres palabras en común exigidas, con solo dos coincidentes, no hay candidata', 0,
    count(Cruce::candidatos([$ficha('Ana Pérez', '1850')], [$ficha('Ana Pérez Soto', '1850')], 'spa', new OpcionesCruce(0.6, 5, 3))['candidatos']));
$parcialA = $ficha('Ana Pérez', '1850');
$parcialB = $ficha('Ana Pérez Soto', '1853');
igual('opciones: con 2 de 3 palabras (puntaje 0,67), el mínimo por defecto la acepta', 1, count(Cruce::candidatos([$parcialA], [$parcialB])['candidatos']));
igual('opciones: un puntaje mínimo de 0,9 la descarta', 0, count(Cruce::candidatos([$parcialA], [$parcialB], 'spa', new OpcionesCruce(0.9))['candidatos']));
foreach ([[0.0, 5, 2], [1.5, 5, 2], [0.6, -1, 2], [0.6, 5, 0]] as [$u, $an, $pal]) {
    try {
        new OpcionesCruce($u, $an, $pal);
        igual("opciones: ($u, $an, $pal) lanza", true, false);
    } catch (InvalidArgumentException) {
        igual("opciones: ($u, $an, $pal) lanza", true, true);
    }
}
$padre = [['relacion' => 'padre', 'nombre' => 'Pedro Pérez', 'id' => 'Q1']];
$conPadreA = Cruce::candidatos([$ficha('Ana Pérez', '1850', null, $padre)], [$ficha('Ana Pérez Soto', '1851', null, $padre)])['candidatos'];
$sinPadre = Cruce::candidatos([$ficha('Ana Pérez', '1850')], [$ficha('Ana Pérez Soto', '1851')])['candidatos'];
igual('parientes: el mismo padre por nombre suma evidencia', true, in_array('comparten un pariente por nombre', $conPadreA[0]['evidencia'] ?? [], true));
igual('parientes: y sube el puntaje', true, ($conPadreA[0]['puntaje'] ?? 0) > ($sinPadre[0]['puntaje'] ?? 1) || ($conPadreA[0]['puntaje'] ?? 0) === 1.0);
$otroPadre = Cruce::candidatos([$ficha('Ana Pérez', '1850', null, $padre)], [$ficha('Ana Pérez Soto', '1851', null, [['relacion' => 'padre', 'nombre' => 'Juan Rojas', 'id' => 'Q9']])])['candidatos'];
igual('parientes: un padre con otro nombre no suma', false, in_array('comparten un pariente por nombre', $otroPadre[0]['evidencia'] ?? [], true));
$sinNombre = Cruce::candidatos([$ficha('Ana Pérez', '1850', null, [['relacion' => 'padre', 'nombre' => null, 'id' => 'Q1']])], [$ficha('Ana Pérez Soto', '1851', null, [['relacion' => 'padre', 'nombre' => null, 'id' => 'Q1']])])['candidatos'];
igual('parientes: sin nombre (solo un identificador) no se compara', false, in_array('comparten un pariente por nombre', $sinNombre[0]['evidencia'] ?? [], true));
$generador = static function () use ($pa) {
    yield $pa;
};
$indiceB = Cruce::indexar([$pb]);
$omitidosGen = 0;
igual('cruce: A puede ser un generador y B un índice armado aparte', 1, count(Cruce::cruzar($generador(), $indiceB, 'spa', new OpcionesCruce(), $omitidosGen)));
$vivoParaIndice = $ficha('Fulano Vivo', '1990');
$vivoParaIndice['posiblemente_vivo'] = true;
$indiceConVivo = Cruce::indexar([$pb, $vivoParaIndice]);
igual('cruce: el índice omite a los que pueden estar vivos y los cuenta', [1, 1], [count($indiceConVivo->fichas), $indiceConVivo->omitidos]);
igual('cruce: el índice solo guarda una referencia corta, no el perfil entero', ['red', 'url', 'nombre', 'nacimiento', 'muerte'], array_keys($indiceConVivo->fichas[0]['ref']));

// --- Sensibles en seis idiomas (punto 7) ---
foreach ([
    ['francés', 'Je suis catholique et j\'ai un cancer', ['salud', 'religión']],
    ['portugués', 'Sou católica e tenho depressão', ['salud', 'religión']],
    ['italiano', 'Sono ebreo e ho il diabete', ['salud', 'religión']],
    ['alemán', 'Ich bin jüdisch und habe Krebs', ['salud', 'religión']],
    ['alemán política', 'Mitglied der Gewerkschaft, Sinti', ['política o sindical', 'origen étnico']],
    ['francés vida sexual', 'Je suis lesbienne', ['vida sexual']],
    ['italiano salud', 'Ho una malattia cronica', ['salud']],
    ['portugués origen', 'Sou de origem cigana e quilombola', ['origen étnico']],
] as [$idioma, $frase, $esperadas]) {
    igual("sensibles ($idioma): $frase", $esperadas, Sensibles::buscar($frase));
}
foreach (['Historia de Roma y de la Iglesia', 'Histoire de Rome et de son église', 'Genealogista e arquivista', 'Archivar und Familienforscher', 'Storia della famiglia e dei registri'] as $limpia) {
    igual("sensibles: «{$limpia}» no marca nada", [], Sensibles::buscar($limpia));
}

// --- BillionGraves y obituarios (punto 9) ---
$bg = Detector::analizar(muestra('billiongraves'))->aArreglo();
igual('billiongraves: red, número de registro y nombre', ['billiongraves', '12345678', 'Maria Ficticia'], [$bg['red'], $bg['usuario'], $bg['nombre']]);
igual('billiongraves: fechas y lugar de una fallecida', ['1851-04-02', '1921-09-30', 'Valparaiso, Chile', false], [$bg['nacimiento'], $bg['muerte'], $bg['lugar_nacimiento'], $bg['posiblemente_vivo']]);
$ob = Detector::analizar(muestra('obituario'))->aArreglo();
igual('obituario: lo toma el analizador genérico (JSON-LD con deathDate)', ['generico', 'Ana Ficticia', '2020-03-02', false], [$ob['red'], $ob['nombre'], $ob['muerte'], $ob['posiblemente_vivo']]);
igual('obituario: el correo y el teléfono no salen', false, str_contains((string) json_encode($ob), 'familia@') || str_contains((string) json_encode($ob), '+56 9'));

// --- Diagnóstico para validar contra páginas reales (punto 1) ---
$inf = Diagnostico::informe(muestra('facebook'));
igual('diagnóstico: qué analizador y qué campos', ['facebook', true, true, false], [$inf['analizador'], $inf['campos']['nombre'], $inf['campos']['descripcion'], $inf['campos']['titular']]);
igual('diagnóstico: lista las etiquetas que encontró', ['og:title', 'og:description'], $inf['etiquetas']);
igual('diagnóstico: la URL sale con la ruta enmascarada', 'https://www.facebook.com/xxx.xxxxxxx/', $inf['url']);
igual('diagnóstico: el informe no trae el nombre ni el usuario', false, str_contains((string) json_encode($inf), 'ana.ejemplo') || str_contains((string) json_encode($inf), 'Ana Ejemplo'));
igual('diagnóstico: una página que ningún analizador reconoce lo dice', [null, true], [Diagnostico::informe(muestra('desconocida'))['analizador'], Diagnostico::informe(muestra('desconocida'))['avisos'] !== []]);
igual('diagnóstico: detecta JSON-LD y la persona', [true, true], [Diagnostico::informe(muestra('obituario'))['datos_estructurados']['json_ld'], Diagnostico::informe(muestra('obituario'))['datos_estructurados']['persona']]);
$esq = Diagnostico::esqueleto(muestra('twitter'));
igual('esqueleto: conserva las etiquetas y la forma, sin letras ni dígitos reales', true, str_contains($esq, 'etiqueta og:title = xxxx xxxxxxx (@xxxxxxxxxxx) / x') && !str_contains($esq, 'Dana') && !str_contains($esq, 'danaejemplo'));
igual('esqueleto: enmascara dígitos y conserva la puntuación', 'xxx-99 (xx) 9', Diagnostico::enmascarar('Ana-12 (de) 7'));
igual('esqueleto: enmascara también letras con tilde y eñes', 'xxxxx xxxxx', Diagnostico::enmascarar('María Muñoz'));
igual('esqueleto: de una persona fallecida enmascara también sus fechas y lugares', true, str_contains(Diagnostico::esqueleto(muestra('billiongraves')), 'persona.muerte = 9999-99-99') && !str_contains(Diagnostico::esqueleto(muestra('billiongraves')), '1921'));

// --- Auditoría: la lista de exclusión no puede dejar pasar a la misma persona ---
$mismaPagina = [
    'con barra final' => 'https://www.facebook.com/ana.ejemplo/',
    'sin www' => 'https://facebook.com/ana.ejemplo',
    'versión móvil' => 'https://m.facebook.com/ana.ejemplo',
    'con mobile.' => 'https://mobile.facebook.com/ana.ejemplo',
    'con parámetro ref' => 'https://www.facebook.com/ana.ejemplo?ref=bookmarks',
    'con fbclid' => 'https://www.facebook.com/ana.ejemplo/?fbclid=ABC123',
    'con utm_*' => 'https://www.facebook.com/ana.ejemplo?utm_source=x&utm_medium=y',
    'con fragmento' => 'https://www.facebook.com/ana.ejemplo#about',
    'con mayúsculas' => 'https://www.facebook.com/Ana.Ejemplo',
    'con http' => 'http://www.facebook.com/ana.ejemplo',
];
$listaFb = Exclusiones::desdeTexto("https://www.facebook.com/ana.ejemplo\n");
foreach ($mismaPagina as $que => $url) {
    igual("exclusión: la misma página $que", true, $listaFb->contiene(['url' => $url, 'red' => 'otra', 'usuario' => 'otro']));
}
igual('claveUrl: profile.php?id=1 y ?id=2 son personas distintas', true, Lote::claveUrl('https://www.facebook.com/profile.php?id=1') !== Lote::claveUrl('https://www.facebook.com/profile.php?id=2'));
igual('claveUrl: el id de profile.php se conserva aunque haya rastreo', Lote::claveUrl('https://facebook.com/profile.php?id=7'), Lote::claveUrl('https://m.facebook.com/profile.php?utm_source=x&id=7&fbclid=Z'));
igual('claveUrl: dos páginas distintas no se confunden', true, Lote::claveUrl('https://x.com/ana') !== Lote::claveUrl('https://x.com/ana2'));
$cuentas = ['twitter:danaejemplo', 'twitter:@danaejemplo', 'Twitter:@DanaEjemplo', "twitter:@danaejemplo\r\n"];
foreach ($cuentas as $entrada) {
    igual('exclusión: «' . trim($entrada) . '» excluye a la cuenta', true,
        Exclusiones::desdeTexto($entrada . "\n")->contiene(['url' => 'https://x.com/danaejemplo', 'red' => 'twitter', 'usuario' => 'danaejemplo']));
}
$conBom = Exclusiones::desdeTexto("\xEF\xBB\xBFhttps://www.facebook.com/ana.ejemplo\r\ntwitter:@dana\r\n");
igual('exclusión: un archivo con BOM y fin de línea de Windows se lee entero', [2, true], [$conBom->cantidad(), $conBom->contiene(['url' => 'https://facebook.com/ana.ejemplo', 'red' => 'x', 'usuario' => 'y'])]);
igual('exclusión: una lista solo con comentarios está vacía', [true, false], [Exclusiones::desdeTexto("# nada\n\n")->estaVacia(), $conBom->estaVacia()]);
$vistos = [];
igual('repetida: la primera vez no, la segunda (con otra forma de la URL) sí', [false, true, true], [
    Lote::repetida('https://www.instagram.com/ana/', $vistos),
    Lote::repetida('https://instagram.com/ana?igshid=XYZ', $vistos),
    Lote::repetida('https://m.instagram.com/Ana#x', $vistos),
]);
igual('repetida: una URL nula nunca es repetida', [false, false], [Lote::repetida(null, $vistos), Lote::repetida(null, $vistos)]);

// --- Auditoría: una publicación no es un perfil ---
$conTexto = static fn (string $url, string $titulo): Perfil => Detector::analizar(
    '<link rel="canonical" href="' . $url . '"><meta property="og:title" content="' . $titulo . '"><meta property="og:description" content="TEXTO DE LA PAGINA">',
    null,
    false
);
foreach ([
    ['twitter', 'https://x.com/ana/status/123', 'Ana (@ana) on X'],
    ['facebook /posts/', 'https://www.facebook.com/ana.ejemplo/posts/998877', 'Ana'],
    ['facebook photo.php', 'https://www.facebook.com/photo.php', 'Ana'],
    ['instagram /p/', 'https://www.instagram.com/p/ABC123/', 'Ana (@ana) • Instagram'],
    ['tiktok video', 'https://www.tiktok.com/@ana/video/1234567890', 'Ana (@ana) | TikTok'],
    ['youtube watch', 'https://www.youtube.com/watch', 'Un video'],
    ['linkedin posts', 'https://www.linkedin.com/posts/ana-ejemplo_algo-123', 'Ana - Cargo | LinkedIn'],
    ['threads post', 'https://www.threads.net/@ana/post/Cxyz', 'Ana (@ana) • Threads'],
    ['bluesky post', 'https://bsky.app/profile/ana.bsky.social/post/3k', 'Ana (@ana.bsky.social)'],
    ['pinterest pin', 'https://www.pinterest.com/pin/12345/', 'Un pin'],
] as [$red, $url, $titulo]) {
    $pub = $conTexto($url, $titulo);
    igual("publicación ($red): no se guarda su texto y se avisa", [null, true], [
        $pub->descripcion,
        in_array('la dirección es de una publicación, no de un perfil: su texto no se guarda (ni descripción, ni nombre, ni titular, ni lugar)', $pub->avisos, true),
    ]);
}
foreach ([
    ['twitter', 'https://x.com/ana', 'Ana (@ana) / X'],
    ['facebook', 'https://www.facebook.com/ana.ejemplo', 'Ana'],
    ['tiktok', 'https://www.tiktok.com/@ana', 'Ana (@ana) | TikTok'],
    ['youtube', 'https://www.youtube.com/@ana', 'Ana'],
    ['linkedin', 'https://www.linkedin.com/in/ana-ejemplo', 'Ana - Cargo | LinkedIn'],
    ['bluesky', 'https://bsky.app/profile/ana.bsky.social', 'Ana (@ana.bsky.social)'],
] as [$red, $url, $titulo]) {
    igual("perfil ($red): sí conserva su descripción", 'TEXTO DE LA PAGINA', $conTexto($url, $titulo)->descripcion);
}
igual('publicación: el nombre tampoco se guarda (el título puede ser el texto), pero el usuario sí', [null, 'ana'], [$conTexto('https://x.com/ana/status/123', 'Ana (@ana) on X')->nombre, $conTexto('https://x.com/ana/status/123', 'Ana (@ana) on X')->usuario]);

// --- Auditoría: sin texto no se llama al filtro ni se avisa de su falta ---
$filtroAntes = getenv('FILTRO_DIFAMACION_DIR');
putenv('FILTRO_DIFAMACION_DIR=/no/existe/el/filtro');
$sinFiltroSinTexto = Detector::analizar('<link rel="canonical" href="https://x.com/uno"><meta property="og:title" content="Uno (@uno) / X">')->avisos;
$sinFiltroConTexto = Detector::analizar('<link rel="canonical" href="https://x.com/uno"><meta property="og:title" content="Uno (@uno) / X"><meta property="og:description" content="Algo">')->avisos;
putenv($filtroAntes === false ? 'FILTRO_DIFAMACION_DIR' : 'FILTRO_DIFAMACION_DIR=' . $filtroAntes);
$avisoFiltro = 'sin filtro de difamación (falta ../defamatory-content-review/): solo se marcan términos sensibles';
igual('revisión: un perfil sin texto no avisa de que falta el filtro (ni lo llama)', false, in_array($avisoFiltro, $sinFiltroSinTexto, true));
igual('revisión: un perfil con texto sí avisa de que falta el filtro', true, in_array($avisoFiltro, $sinFiltroConTexto, true));

// --- Revisión independiente (code-review) del PR: ocho hallazgos ---
// 1) más formas de publicación, y un perfil real con pestaña de fotos NO es una publicación
$avisoPublicacion = 'la dirección es de una publicación, no de un perfil: su texto no se guarda (ni descripción, ni nombre, ni titular, ni lugar)';
foreach ([
    ['youtu.be', 'https://youtu.be/dQw4w9WgXcQ', 'Un video'],
    ['youtube /clip/', 'https://www.youtube.com/clip/UgkxABC', 'Un clip'],
    ['youtube /playlist', 'https://www.youtube.com/playlist', 'Una lista'],
    ['instagram /usuario/p/', 'https://www.instagram.com/ana/p/ABC123/', 'Ana (@ana) • Instagram'],
    ['instagram /usuario/reel/', 'https://www.instagram.com/ana/reel/ABC123/', 'Ana (@ana) • Instagram'],
    ['facebook /usuario/photos/ID', 'https://www.facebook.com/ana.ejemplo/photos/123456', 'Ana'],
    ['facebook /groups/…/posts/', 'https://www.facebook.com/groups/12345/posts/678', 'Un grupo'],
    ['facebook /watch', 'https://www.facebook.com/watch/?v=123', 'Un video'],
] as [$red, $url, $titulo]) {
    $pub = $conTexto($url, $titulo);
    igual("publicación ($red): sin descripción y con aviso", [null, true], [$pub->descripcion, in_array($avisoPublicacion, $pub->avisos, true)]);
}
foreach (['https://www.facebook.com/ana.ejemplo/photos', 'https://www.facebook.com/ana.ejemplo/videos', 'https://youtu.be/', 'https://www.instagram.com/ana/reels/', 'https://www.facebook.com/p/Ana-Ejemplo-100000000000001/'] as $perfilReal) {
    igual("no es una publicación: $perfilReal", false, in_array($avisoPublicacion, $conTexto($perfilReal, 'Ana')->avisos, true));
}
// 4) el título de una publicación puede ser su texto: no llega ni al nombre ni a las marcas
$fuga = Detector::analizar('<link rel="canonical" href="https://www.facebook.com/ana.ejemplo/posts/998877"><meta property="og:title" content="Mi número es 912345678, llámame">');
igual('publicación: el teléfono del título no llega al nombre ni a las marcas', [null, []], [$fuga->nombre, $fuga->marcas]);
igual('publicación: no avisa «sin nombre» (no es un perfil)', false, str_starts_with(implode('|', $fuga->avisos), 'sin nombre') || in_array('sin nombre: la página no trae las etiquetas que se leen; quizá pide iniciar sesión', $fuga->avisos, true));
// 2) enlaces antiguos con #!/ : cada perfil con su clave
igual('claveUrl: dos perfiles con #!/ no se confunden con el dominio', true, Lote::claveUrl('https://twitter.com/#!/ana') !== Lote::claveUrl('https://twitter.com/#!/beto') && Lote::claveUrl('https://twitter.com/#!/ana') !== 'twitter.com');
igual('claveUrl: un fragmento normal sigue ignorándose', Lote::claveUrl('https://x.com/ana'), Lote::claveUrl('https://x.com/ana#about'));
$vistosHash = [];
igual('repetida: dos perfiles con #!/ distintos no son repetidos', [false, false, true], [Lote::repetida('https://twitter.com/#!/ana', $vistosHash), Lote::repetida('https://twitter.com/#!/beto', $vistosHash), Lote::repetida('https://twitter.com/#!/ana', $vistosHash)]);
// 3) los parámetros de rastreo se descartan solo donde lo son
igual('claveUrl: ?t= o ?s= en otro sitio elige la página: no se descarta', true, Lote::claveUrl('https://example.org/perfil?t=ana') !== Lote::claveUrl('https://example.org/perfil?t=beto'));
igual('claveUrl: en X, ?s= y ?t= sí son de rastreo', Lote::claveUrl('https://x.com/ana'), Lote::claveUrl('https://x.com/ana?s=20&t=AbCdEf'));
igual('claveUrl: en Facebook, ?ref= es de rastreo; en otro sitio no', [true, false], [
    Lote::claveUrl('https://www.facebook.com/ana?ref=bookmarks') === Lote::claveUrl('https://facebook.com/ana'),
    Lote::claveUrl('https://example.org/ana?ref=1') === Lote::claveUrl('https://example.org/ana'),
]);
igual('claveUrl: en YouTube, ?si= es de rastreo', Lote::claveUrl('https://youtube.com/@ana'), Lote::claveUrl('https://youtube.com/@ana?si=XYZ'));
// 8) los identificadores que distinguen mayúsculas no se confunden al deduplicar; la exclusión sigue siendo de más
$vistosYt = [];
igual('repetida: dos videos cuyo id solo difiere en mayúsculas no son repetidos', [false, false], [Lote::repetida('https://youtube.com/watch?v=aBcDeFgHiJk', $vistosYt), Lote::repetida('https://youtube.com/watch?v=ABCDEFGHIJK', $vistosYt)]);
igual('repetida: el usuario (primer tramo) sí ignora mayúsculas', [false, true], [Lote::repetida('https://www.instagram.com/Ana/p/AbC/', $vistosYt), Lote::repetida('https://instagram.com/ana/p/AbC', $vistosYt)]);
igual('exclusión: sigue ignorando mayúsculas (excluir de más antes que de menos)', true, Exclusiones::desdeTexto("https://youtube.com/watch?v=abcdefghijk\n")->contiene(['url' => 'https://youtube.com/watch?v=ABCDEFGHIJK', 'red' => 'a', 'usuario' => 'b']));
// 6) «twitter:@» sin usuario falla en voz alta
foreach (['twitter:@', 'twitter:@@', "x:@\n"] as $mala) {
    try {
        Exclusiones::desdeTexto($mala);
        igual("exclusión: «" . trim($mala) . "» lanza", true, false);
    } catch (RuntimeException $e) {
        igual("exclusión: «" . trim($mala) . "» lanza y dice que falta el usuario", true, str_contains($e->getMessage(), 'falta el usuario'));
    }
}
// 7) un objeto vacío {} no es una línea ilegible
igual('purga: «{}» no es ilegible y se conserva tal cual', [false, '{}'], [Purga::linea('{}', $excluirPurga)['ilegible'], Purga::linea('{}', $excluirPurga)['linea']]);
igual('purga: un arreglo con datos sigue siendo ilegible', true, Purga::linea('[1]', $excluirPurga)['ilegible']);

// --- Validado contra entidades reales de Wikidata: la etiqueta universal es «mul», no «la primera que haya» ---
$conMul = [
    'id' => 'Q9001', 'type' => 'item',
    // como una entidad real de hoy: sin «es» ni «en», con «mul» y otras escrituras (la primera por orden alfabético es la árabe)
    'labels' => ['ar' => ['language' => 'ar', 'value' => 'ماريا فيكتيسيا'], 'ja' => ['language' => 'ja', 'value' => 'マリア'], 'mul' => ['language' => 'mul', 'value' => 'Maria Ficticia']],
    'descriptions' => ['ar' => ['language' => 'ar', 'value' => 'وصف'], 'en' => ['language' => 'en', 'value' => 'invented person']],
    'claims' => [
        'P31' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q5']]], 'rank' => 'normal']],
        'P570' => [['mainsnak' => ['datavalue' => ['value' => ['time' => '+1900-05-05T00:00:00Z', 'precision' => 11]]], 'rank' => 'normal']],
    ],
];
$perfilMul = Wikidata::persona($conMul);
igual('wikidata: sin etiqueta es ni en, usa mul y no la primera (árabe)', ['Maria Ficticia', 'invented person'], [$perfilMul?->nombre, $perfilMul?->descripcion]);
$conEs = $conMul;
$conEs['labels']['es'] = ['language' => 'es', 'value' => 'María Ficticia'];
igual('wikidata: si hay etiqueta en español, esa gana sobre mul', 'María Ficticia', Wikidata::persona($conEs)?->nombre);
$soloOtras = $conMul;
unset($soloOtras['labels']['mul']);
igual('wikidata: sin es, en ni mul, cae a la primera que haya', 'ماريا فيكتيسيا', Wikidata::persona($soloOtras)?->nombre);

// --- Guarda: una comilla angular pegada a una variable en un texto con comillas dobles hace que PHP la lea como parte del nombre (pasó cuatro veces) ---
$conVariableMal = [];
foreach ([...(glob(__DIR__ . '/../src/*.php') ?: []), ...(glob(__DIR__ . '/../bin/*.php') ?: []), __FILE__] as $archivoRevisado) {
    if (preg_match('/\$[A-Za-z_]\w*[\x80-\xFF]/', (string) file_get_contents($archivoRevisado)) === 1) {
        $conVariableMal[] = basename($archivoRevisado);
    }
}
igual('código: ninguna variable lleva pegado un carácter no ASCII (PHP lo lee como parte del nombre)', [], $conVariableMal);

// CLI: no descarga de la red
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' https://www.facebook.com/alguien 2>&1', $salida, $codigo);
igual('CLI: rechaza una URL con código 2, como los demás programas', 2, $codigo);
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
// Streaming: la salida del programa es la de resolverNombres(), y un archivo grande no sube la memoria.
$muchos = [];
for ($i = 0; $i < 3000; $i++) {
    $muchos[] = ['red' => 'wikidata', 'url' => "https://www.wikidata.org/wiki/Q{$i}0001", 'usuario' => "Q{$i}0001", 'nombre' => "Ficticio {$i}",
        'lugar_nacimiento' => 'Q2887', 'avisos' => [], 'parientes' => [['relacion' => 'padre', 'nombre' => null, 'id' => 'Q2']]];
}
file_put_contents("$tmp/wd-muchos.jsonl", implode("\n", array_map(static fn (array $p): string => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $muchos)) . "\n");
$esperado = Wikidata::resolverNombres($volcadoNombres, $muchos)['perfiles'];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/wikidata-nombres.php') . ' ' . escapeshellarg($volcadoNombres) . ' ' . escapeshellarg("$tmp/wd-muchos.jsonl") . ' 2>/dev/null', $salidaMuchos, $codigo);
$obtenido = array_map(static fn (string $l): mixed => json_decode($l, true), $salidaMuchos);
igual('wikidata-nombres.php (streaming): 3000 perfiles dan lo mismo que resolverNombres()', [0, true], [$codigo, $obtenido === json_decode(json_encode($esperado, JSON_UNESCAPED_UNICODE) ?: '', true)]);
igual('wikidata-nombres.php (streaming): un archivo de perfiles que no existe sale con 1', 1, (static function () use ($volcadoNombres, $tmp): int {
    exec('php ' . escapeshellarg(__DIR__ . '/../bin/wikidata-nombres.php') . ' ' . escapeshellarg($volcadoNombres) . ' ' . escapeshellarg("$tmp/no-existe.jsonl") . ' 2>/dev/null', $o, $c);
    return $c;
})());

$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --jsonl ' . escapeshellarg(__DIR__ . '/muestras/wikitree-api.json') . ' 2>/dev/null', $salida, $codigo);
igual('analizar.php: un .json de WikiTree suelta sus tres perfiles', [0, 3], [$codigo, count($salida)]);
file_put_contents("$tmp/excluir-wt.txt", "wikitree:Vivo-7\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --jsonl --excluir=' . escapeshellarg("$tmp/excluir-wt.txt") . ' ' . escapeshellarg(__DIR__ . '/muestras/wikitree-api.json') . ' 2>/dev/null', $salida, $codigo);
igual('analizar.php: la lista de exclusión también vale para la API de WikiTree', [0, 2, false], [$codigo, count($salida), str_contains(implode('', $salida), 'Vivo-7')]);
file_put_contents("$tmp/roto.json", 'no es json');
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' ' . escapeshellarg("$tmp/roto.json") . ' 2>/dev/null', $salida, $codigo);
igual('analizar.php: un .json roto sale con 1', 1, $codigo);

// bin/purgar.php
file_put_contents("$tmp/generado.jsonl", implode("\n", [
    '{"red":"facebook","usuario":"ana.ejemplo","url":"https://www.facebook.com/ana.ejemplo"}',
    '{"red":"twitter","usuario":"danaejemplo","url":"https://x.com/danaejemplo"}',
    '{"red":"twitter","usuario":"otra","url":"https://x.com/otra"}',
]) . "\n");
file_put_contents("$tmp/purga.txt", "facebook:ana.ejemplo\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/purgar.php') . ' ' . escapeshellarg("$tmp/generado.jsonl") . ' --excluir=' . escapeshellarg("$tmp/purga.txt") . ' 2>/dev/null', $salida, $codigo);
igual('purgar.php: a la salida estándar quita a una persona y no toca el archivo', [0, 2, 3], [$codigo, count($salida), count(file("$tmp/generado.jsonl") ?: [])]);
exec('php ' . escapeshellarg(__DIR__ . '/../bin/purgar.php') . ' ' . escapeshellarg("$tmp/generado.jsonl") . ' --excluir=' . escapeshellarg("$tmp/purga.txt") . ' --en-sitio 2>/dev/null', $salida, $codigo);
igual('purgar.php --en-sitio reescribe el archivo sin la persona y sin dejar temporales', [0, 2, []], [$codigo, count(file("$tmp/generado.jsonl") ?: []), glob("$tmp/*.tmp") ?: []]);
file_put_contents("$tmp/con-basura.jsonl", "no es json\n" . '{"red":"twitter","usuario":"otra","url":"https://x.com/otra"}' . "\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/purgar.php') . ' ' . escapeshellarg("$tmp/con-basura.jsonl") . ' --excluir=' . escapeshellarg("$tmp/purga.txt") . ' 2>/dev/null', $salida, $codigo);
igual('purgar.php: una línea ilegible se conserva y el programa sale con 1', [1, 2], [$codigo, count($salida)]);
exec('php ' . escapeshellarg(__DIR__ . '/../bin/purgar.php') . ' ' . escapeshellarg("$tmp/generado.jsonl") . ' 2>&1', $salida, $codigo);
igual('purgar.php: sin --excluir sale con 2', 2, $codigo);
// analizar.php sin --jsonl sigue dando un solo arreglo JSON
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' ' . escapeshellarg(__DIR__ . '/muestras/twitter.html') . ' ' . escapeshellarg(__DIR__ . '/muestras/facebook.html') . ' 2>/dev/null', $salida, $codigo);
$arreglo = json_decode(implode("\n", $salida), true);
igual('analizar.php sin --jsonl: un solo arreglo JSON con las dos páginas', [0, true, 2], [$codigo, is_array($arreglo) && array_is_list($arreglo), is_array($arreglo) ? count($arreglo) : -1]);
file_put_contents("$tmp/excluir-cruce.txt", "wikitree:Gonzalez-1234\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/cruzar.php') . ' ' . escapeshellarg("$tmp/a.jsonl") . ' ' . escapeshellarg("$tmp/b.jsonl") . ' --excluir=' . escapeshellarg("$tmp/excluir-cruce.txt") . ' 2>/dev/null', $salida, $codigo);
igual('cruzar.php --excluir: la persona excluida no genera candidatos', [0, 0], [$codigo, count($salida)]);

foreach ([['--umbral=0', 'umbral cero'], ['--umbral=2', 'umbral mayor que 1'], ['--anios=-3', 'años negativos'], ['--palabras=0', 'cero palabras']] as [$bandera, $que]) {
    $salida = [];
    exec('php ' . escapeshellarg(__DIR__ . '/../bin/cruzar.php') . ' ' . escapeshellarg("$tmp/a.jsonl") . ' ' . escapeshellarg("$tmp/b.jsonl") . ' ' . $bandera . ' 2>&1', $salida, $codigo);
    igual("cruzar.php: $que sale con 2 y lo explica", [2, true], [$codigo, $salida !== []]);
}
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/cruzar.php') . ' ' . escapeshellarg("$tmp/a.jsonl") . ' ' . escapeshellarg("$tmp/no-existe.jsonl") . ' 2>&1', $salida, $codigo);
igual('cruzar.php: un archivo que no existe sale con 1', 1, $codigo);
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/cruzar.php') . ' ' . escapeshellarg("$tmp/a.jsonl") . ' ' . escapeshellarg("$tmp/b.jsonl") . ' --anios=0 2>/dev/null', $salida, $codigo);
igual('cruzar.php --anios=0: la pareja de 1850 y 1851 deja de ser candidata', [0, 0], [$codigo, count($salida)]);

$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/diagnostico.php') . ' ' . escapeshellarg(__DIR__ . '/muestras/facebook.html') . ' 2>/dev/null', $salida, $codigo);
$informeCli = json_decode(implode("\n", $salida), true);
igual('diagnostico.php: escribe el informe en JSON', [0, 'facebook'], [$codigo, is_array($informeCli) ? ($informeCli['analizador'] ?? null) : null]);
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/diagnostico.php') . ' --esqueleto ' . escapeshellarg(__DIR__ . '/muestras/twitter.html') . ' 2>/dev/null', $salida, $codigo);
igual('diagnostico.php --esqueleto: sin datos reales', [0, false], [$codigo, str_contains(implode("\n", $salida), 'danaejemplo')]);
exec('php ' . escapeshellarg(__DIR__ . '/../bin/diagnostico.php') . ' https://x.com/alguien 2>&1', $salida, $codigo);
igual('diagnostico.php: rechaza una URL', 2, $codigo);
exec('php ' . escapeshellarg(__DIR__ . '/../bin/diagnostico.php') . ' ' . escapeshellarg("$tmp/no-existe.html") . ' 2>&1', $salida, $codigo);
igual('diagnostico.php: un archivo que no existe sale con 1', 1, $codigo);

// --- Auditoría: los programas hablan igual ---
file_put_contents("$tmp/vacia.txt", "# nadie por ahora\n\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --jsonl --excluir=' . escapeshellarg("$tmp/vacia.txt") . ' ' . escapeshellarg(__DIR__ . '/muestras/twitter.html') . ' 2>&1 >/dev/null', $salida, $codigo);
igual('analizar.php: una lista de exclusión sin entradas avisa y no falla', [0, true], [$codigo, str_contains(implode("\n", $salida), 'no tiene ninguna entrada')]);
foreach (['purgar' => "$tmp/generado.jsonl", 'cruzar' => "$tmp/a.jsonl"] as $programa => $entrada) {
    $salida = [];
    $extra = $programa === 'cruzar' ? ' ' . escapeshellarg("$tmp/b.jsonl") : '';
    exec('php ' . escapeshellarg(__DIR__ . "/../bin/$programa.php") . ' ' . escapeshellarg($entrada) . $extra . ' --excluir=' . escapeshellarg("$tmp/vacia.txt") . ' 2>&1 >/dev/null', $salida, $codigo);
    igual("$programa.php: la lista sin entradas avisa", true, str_contains(implode("\n", $salida), 'no tiene ninguna entrada'));
}
file_put_contents("$tmp/con-ilegibles.jsonl", "esto no es json\n" . (string) file_get_contents("$tmp/a.jsonl") . "[1,2,3]\n");
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/cruzar.php') . ' ' . escapeshellarg("$tmp/con-ilegibles.jsonl") . ' ' . escapeshellarg("$tmp/b.jsonl") . ' 2>&1 >/dev/null', $salida, $codigo);
igual('cruzar.php: cuenta y avisa las líneas que no son un objeto JSON (2), como purgar.php', true, str_contains(implode("\n", $salida), '2 líneas que no son un objeto JSON'));
// analizar.php: la misma página en dos formas de la URL sale una vez
$variantes = ['https://www.facebook.com/ana.ejemplo/', 'https://m.facebook.com/ana.ejemplo?ref=bookmarks#about'];
mkdir("$tmp/variantes");
foreach ($variantes as $n => $urlVariante) {
    file_put_contents("$tmp/variantes/p$n.html", '<link rel="canonical" href="' . $urlVariante . '"><meta property="og:title" content="Ana Ejemplo">');
}
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --jsonl ' . escapeshellarg("$tmp/variantes") . ' 2>/dev/null', $salida, $codigo);
igual('analizar.php: la misma página con dos formas de URL sale una vez', [0, 1], [$codigo, count($salida)]);
array_map('unlink', glob("$tmp/variantes/*") ?: []);
rmdir("$tmp/variantes");

file_put_contents("$tmp/con-vacios.jsonl", "{}\n" . (string) file_get_contents("$tmp/a.jsonl"));
$salida = [];
exec('php ' . escapeshellarg(__DIR__ . '/../bin/cruzar.php') . ' ' . escapeshellarg("$tmp/con-vacios.jsonl") . ' ' . escapeshellarg("$tmp/b.jsonl") . ' 2>&1 >/dev/null', $salida, $codigo);
igual('cruzar.php: un «{}» no se cuenta como línea que no es un objeto JSON', false, str_contains(implode("\n", $salida), 'no son un objeto JSON') || str_contains(implode("\n", $salida), 'no es un objeto JSON'));

// --- Los seis programas rechazan lo que no entienden, en vez de tratarlo como un archivo ---
$programas = [
    'analizar' => [__DIR__ . '/muestras/twitter.html'],
    'cruzar' => ["$tmp/a.jsonl", "$tmp/b.jsonl"],
    'purgar' => ["$tmp/generado.jsonl"],
    'wikidata' => [__DIR__ . '/muestras/wikidata-muestra.json'],
    'wikidata-nombres' => [__DIR__ . '/muestras/wikidata-nombres-muestra.json', "$tmp/wd.jsonl"],
    'diagnostico' => [__DIR__ . '/muestras/twitter.html'],
];
$ejecutar = static function (string $programa, array $argumentos) use ($tmp): array {
    $partes = [];
    foreach ($argumentos as $argumento) {
        if (is_string($argumento)) {
            $partes[] = escapeshellarg($argumento);
        }
    }
    $salida = [];
    exec('php ' . escapeshellarg(__DIR__ . "/../bin/$programa.php") . ' ' . implode(' ', $partes) . ' 2>' . escapeshellarg("$tmp/stderr.txt"), $salida, $codigo);
    // [código de salida, salida estándar (con su salto de línea final), salida de errores]
    return [$codigo, implode("\n", $salida) . ($salida === [] ? '' : "\n"), (string) @file_get_contents("$tmp/stderr.txt")];
};
foreach ($programas as $programa => $entradas) {
    [$codigo, $stdout, $stderr] = $ejecutar($programa, [...$entradas, '--opcion-que-no-existe']);
    igual("$programa.php: una opción desconocida sale con 2, lo dice y no escribe nada", [2, true, ''], [$codigo, str_contains($stderr, 'Opción desconocida «--opcion-que-no-existe»'), $stdout]);
    [$codigo, $stdout] = $ejecutar($programa, ['--help']);
    igual("$programa.php --help escribe el uso y sale con 0", [0, true], [$codigo, str_starts_with($stdout, 'Uso: php bin/' . $programa . '.php')]);
}
foreach (['analizar', 'cruzar', 'purgar', 'wikidata', 'wikidata-nombres'] as $programa) {
    [$codigo, $stdout, $stderr] = $ejecutar($programa, ['--excluir', "$tmp/purga.txt", ...$programas[$programa]]);
    igual("$programa.php: --excluir con espacio (en vez de =) sale con 2 y no escribe nada: la exclusión no se pierde en silencio", [2, true, ''], [$codigo, str_contains($stderr, '--excluir pide un valor y se escribe junto'), $stdout]);
}
foreach ([
    ['wikidata', ['--max=abc'], '--max pide un número entero'],
    ['wikidata', ['--max=0'], '--max pide un número entero'],
    ['wikidata', ['--capturado=ayer'], '--capturado pide una fecha real'],
    ['wikidata', ['--capturado=2026-02-30'], '--capturado pide una fecha real'],
    ['analizar', ['--capturado=2026-13-01'], '--capturado pide una fecha real'],
    ['cruzar', ['--anios=abc'], '--anios pide un número entero'],
    ['cruzar', ['--palabras=0'], '--palabras pide un número entero'],
    ['cruzar', ['--umbral=x'], '--umbral pide un número'],
] as [$programa, $extra, $mensaje]) {
    [$codigo, $stdout, $stderr] = $ejecutar($programa, [...$programas[$programa], ...$extra]);
    igual("$programa.php {$extra[0]}: sale con 2 con «{$mensaje}» y no escribe nada", [2, true, ''], [$codigo, str_contains($stderr, $mensaje), $stdout]);
}
[$codigo, $stdout] = $ejecutar('wikidata', [...$programas['wikidata'], '--max=1', '--capturado=2026-11-20']);
igual('wikidata.php: --max=1 y una fecha real sí funcionan (1 persona, con su fecha de captura)', [0, 1, true], [$codigo, substr_count($stdout, "\n"), str_contains($stdout, '"capturado":"2026-11-20"')]);
[$codigo, $stdout, $stderr] = $ejecutar('cruzar', ['https://example.org/a.jsonl', "$tmp/b.jsonl"]);
igual('cruzar.php: una URL en vez de un archivo sale con 2 y lo explica, como los demás', [2, true, ''], [$codigo, str_contains($stderr, 'esto no descarga archivos'), $stdout]);
foreach (['analizar' => 'https://x.com/ana', 'diagnostico' => 'https://x.com/ana', 'purgar' => 'https://x.com/a.jsonl', 'wikidata' => 'https://dumps.example.org/x.json'] as $programa => $url) {
    [$codigo, $stdout] = $ejecutar($programa, [$url, ...($programa === 'purgar' ? ["--excluir=$tmp/purga.txt"] : [])]);
    igual("$programa.php: una URL en vez de un archivo no escribe nada", [true, ''], [$codigo !== 0, $stdout]);
}
[$codigo, $stdout] = $ejecutar('cruzar', [...$programas['cruzar'], '--anios=0', '--palabras=1', '--umbral=0.5']);
igual('cruzar.php: valores válidos de --anios, --palabras y --umbral se aceptan', 0, $codigo);
array_map('unlink', glob("$tmp/*") ?: []);
rmdir($tmp);

echo Cuenta::$total . " comprobaciones, " . Cuenta::$fallos . " fallos\n";
exit(Cuenta::$fallos > 0 ? 1 : 0);
