<?php

declare(strict_types=1);

// Uso: php bin/wikidata-nombres.php volcado.json[.gz] perfiles.jsonl [--excluir=lista.txt]
// Segundo recorrido del volcado: pone nombre a los lugares y parientes de los perfiles que escribió
// wikidata.php (que los deja como identificadores, p. ej. Q2887). Escribe los perfiles en JSONL.
// El nombre de un pariente solo se resuelve si el volcado lo da por fallecido.

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Exclusiones;
use ParsingFbInMas\Lote;
use ParsingFbInMas\Wikidata;

$archivos = [];
$exclusiones = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--excluir=')) {
        try {
            $exclusiones = Exclusiones::desdeArchivo(substr($arg, 10));
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(2);
        }
    } else {
        $archivos[] = $arg;
    }
}
if (count($archivos) !== 2 || Lote::esUrl($archivos[0]) || Lote::esUrl($archivos[1])) {
    fwrite(STDERR, "Uso: php bin/wikidata-nombres.php volcado.json[.gz] perfiles.jsonl [--excluir=lista.txt]\nEsto no descarga el volcado: bájalo tú.\n");
    exit(2);
}

$lineas = is_file($archivos[1]) ? file($archivos[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
if ($lineas === false) {
    fwrite(STDERR, "{$archivos[1]}: no se pudo leer\n");
    exit(1);
}
$perfiles = [];
foreach ($lineas as $linea) {
    $p = json_decode($linea, true);
    if (is_array($p)) {
        $perfiles[] = array_filter($p, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}

try {
    $r = Wikidata::resolverNombres($archivos[0], $perfiles, $exclusiones);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
foreach ($r['perfiles'] as $p) {
    echo json_encode($p, Lote::JSON), "\n";
}
fwrite(STDERR, count($r['perfiles']) . " perfiles, {$r['resueltos']} nombres resueltos, " . count($r['pendientes']) . " identificadores no están en el volcado\n");
