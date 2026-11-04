<?php

declare(strict_types=1);

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Cruce;
use ParsingFbInMas\Detector;
use ParsingFbInMas\Lote;
use ParsingFbInMas\Metadatos;
use ParsingFbInMas\Wikidata;

$fallos = 0;
$total = 0;

function igual(string $que, mixed $esperado, mixed $real): void
{
    global $fallos, $total;
    $total++;
    if ($esperado !== $real) {
        $fallos++;
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
igual('lote: la carpeta se expande a sus .html', true, count($archivos) >= 10 && str_ends_with($archivos[0], '.html'));
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
exec('php ' . escapeshellarg(__DIR__ . '/../bin/analizar.php') . ' --jsonl ' . escapeshellarg(__DIR__ . '/muestras') . ' 2>/dev/null', $salida, $codigo);
igual('analizar.php: una carpeta entera sale con 1 (hay muestras que fallan a propósito)', 1, $codigo);
array_map('unlink', glob("$tmp/*") ?: []);
rmdir($tmp);

echo "$total comprobaciones, $fallos fallos\n";
exit($fallos > 0 ? 1 : 0);
