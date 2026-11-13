<?php

declare(strict_types=1);

/**
 * Pruebas del port a PHP, sin dependencias. Uso, desde esta carpeta: php pruebas/ejecutar.php
 *
 * Casi todo lo comprueba contra datos/vectores.json, que genera generar_tabla.py con consultas.py: si PHP y
 * Python difieren en una sola de esas respuestas, falla. Aparte van los casos de error y las comprobaciones de
 * que los vectores cubren lo que dicen cubrir.
 */

require __DIR__ . '/../src/Mortalidad.php';

use MortalidadHistorica\Mortalidad;

$fallos = [];
$comprobaciones = 0;

function igual(mixed $esperado, mixed $obtenido, string $donde): void
{
    global $fallos, $comprobaciones;
    $comprobaciones++;
    if ($esperado !== $obtenido) {
        $fallos[] = "$donde: esperado " . json_encode($esperado, JSON_UNESCAPED_UNICODE) . ', obtenido ' . json_encode($obtenido, JSON_UNESCAPED_UNICODE);
    }
}

function casiIgual(?float $esperado, ?float $obtenido, string $donde): void
{
    global $fallos, $comprobaciones;
    $comprobaciones++;
    $bien = $esperado === null || $obtenido === null ? $esperado === $obtenido : abs($esperado - $obtenido) < 1e-9;
    if (!$bien) {
        $fallos[] = "$donde: esperado " . json_encode($esperado) . ', obtenido ' . json_encode($obtenido);
    }
}

function lanza(callable $f, string $donde): void
{
    global $fallos, $comprobaciones;
    $comprobaciones++;
    try {
        $f();
        $fallos[] = "$donde: tendría que lanzar una excepción";
    } catch (InvalidArgumentException) {
        // bien
    }
}

$m = new Mortalidad();
$vectores = json_decode(file_get_contents(__DIR__ . '/../../datos/vectores.json'), true, 512, JSON_THROW_ON_ERROR);

// Causas de defunción: cada término como está, en mayúsculas y unos casos con tildes, ß y vocales árabes.
igual(true, count($vectores['causa_moderna']) > 200, 'vectores: causa_moderna cubre todo el glosario');
foreach ($vectores['causa_moderna'] as [$termino, $idioma, $esperado]) {
    $obtenido = array_map(fn (array $r): array => [$r['causa_id'], $r['confianza'], $r['cie10']], $m->causaModerna($termino, $idioma));
    igual($esperado, $obtenido, "causa_moderna «{$termino}» ($idioma)");
}

// Epidemias por año, región y país.
igual(true, count($vectores['epidemias_en']) > 400, 'vectores: epidemias_en');
foreach ($vectores['epidemias_en'] as [$anio, $region, $pais, $ids]) {
    $obtenido = array_map(fn (array $e): string => $e['id'], $m->epidemiasEn($anio, $region, $pais));
    igual($ids, $obtenido, 'epidemias_en ' . json_encode([$anio, $region, $pais], JSON_UNESCAPED_UNICODE));
}

foreach ($vectores['region_de'] as [$codigo, $region]) {
    igual($region, $m->regionDe($codigo), "region_de $codigo");
}

foreach ($vectores['hitos_de'] as [$codigo, $esperado]) {
    igual($esperado, array_map(fn (array $h): array => [$h['tipo'], (int) $h['desde']], $m->hitosDe($codigo)), "hitos_de $codigo");
}

// Esperanza de vida y supervivencia: cifras y también los «no hay dato».
igual(true, count($vectores['esperanza']) > 500, 'vectores: esperanza');
$conDato = 0;
foreach ($vectores['esperanza'] as [$codigo, $anio, $edad, $sexo, $esperanza, $supervivencia]) {
    casiIgual($esperanza, $m->esperanzaRestante($codigo, $anio, $edad, $sexo), "esperanza $codigo $anio edad $edad $sexo");
    casiIgual($supervivencia, $m->supervivencia($codigo, $anio, $edad, $sexo), "supervivencia $codigo $anio edad $edad $sexo");
    $conDato += $esperanza === null ? 0 : 1;
}
igual(true, $conDato > 100, 'vectores: hay bastantes casos con dato (no solo null)');

// Muertes en Londres y su exceso.
foreach ($vectores['londres'] as [$anio, $totales, $viruela, $exceso]) {
    $obtenido = $m->muertesLondres($anio);
    igual($totales, $obtenido['totales'] ?? null, "londres $anio totales");
    igual($viruela, $obtenido['viruela'] ?? null, "londres $anio viruela");
    casiIgual($exceso, $m->excesoDeMuertesLondres($anio), "londres $anio exceso");
}

// Los ejemplos del README.
casiIgual(36.2, $m->esperanzaRestante('SE', 1805), 'README: esperanza al nacer, Suecia 1800-1809');
casiIgual(47.3, $m->esperanzaRestante('SE', 1805, 5), 'README: esperanza a los 5');
casiIgual(0.687, $m->supervivencia('SE', 1805), 'README: supervivencia a los 5');
igual(['totales' => 97306, 'viruela' => 655], $m->muertesLondres(1665), 'README: Londres 1665');
igual('ictus', $m->causaModerna('Apoplejía', 'spa')[0]['causa_id'], 'README: apoplejía');
igual('peste_negra', $m->epidemiasEn(1348, null, 'IT')[0]['id'], 'README: la peste negra alcanza a Italia');
igual([], $m->epidemiasEn(1348, null, 'CL'), 'README: y no a Chile');

// Normalización: la misma que comun.normalizar.
igual('schlagfluss', Mortalidad::normalizar('  Schlagfluß '), 'normalizar ß');
igual('colera morbo', Mortalidad::normalizar('CÓLERA   morbo'), 'normalizar tildes y espacios');
igual(Mortalidad::normalizar('السل'), Mortalidad::normalizar('اَلسُّلّ'), 'normalizar vocales árabes');

// Errores.
lanza(fn () => $m->causaModerna('tisis', 'es'), 'idioma de dos letras');
lanza(fn () => $m->epidemiasEn(1900, 'Atlantida'), 'región inexistente');
lanza(fn () => $m->epidemiasEn(1900, null, 'ZZ'), 'país inexistente');
lanza(fn () => $m->esperanzaRestante('CL', 1900, 0, 'X'), 'sexo inválido');
lanza(fn () => $m->supervivencia('CL', 1900, 5, 'x'), 'sexo inválido (minúscula)');
igual(null, $m->esperanzaRestante('XX', 1900), 'país sin serie: null, no una excepción');
igual(null, $m->esperanzaRestante('CL', 1900, 20), 'edad que no está: null');
igual(true, $m->esperanzaRestante('CL', 1900, 15) !== null, 'edad 15: hay dato (modelado)');

if ($fallos !== []) {
    fwrite(STDERR, count($fallos) . " fallos de $comprobaciones comprobaciones:\n  - " . implode("\n  - ", array_slice($fallos, 0, 40)) . "\n");
    exit(1);
}
echo "Todo bien: $comprobaciones comprobaciones.\n";
