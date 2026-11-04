<?php

declare(strict_types=1);

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Detector;
use ParsingFbInMas\Metadatos;

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
foreach ([$f, $l, $t, $g] as $perfil) {
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

echo "$total comprobaciones, $fallos fallos\n";
exit($fallos > 0 ? 1 : 0);
