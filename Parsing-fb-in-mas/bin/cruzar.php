<?php

declare(strict_types=1);

// Uso: php bin/cruzar.php a.jsonl b.jsonl [--idioma=spa]
// Propone parejas de personas FALLECIDAS que podrían ser la misma, de dos archivos JSONL
// (la salida de analizar.php --jsonl o de wikidata.php). No fusiona nada. Los perfiles que
// pueden estar vivos se omiten, y se cuenta cuántos.

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Cruce;

$archivos = [];
$idioma = 'spa';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--idioma=')) {
        $idioma = substr($arg, 9);
    } else {
        $archivos[] = $arg;
    }
}
if (count($archivos) !== 2 || !\DefamatoryContentReview\Phonetic\PhoneticFolderRegistry::isSupported($idioma)) {
    fwrite(STDERR, "Uso: php bin/cruzar.php a.jsonl b.jsonl [--idioma=spa]\n");
    exit(2);
}

$listas = [];
foreach ($archivos as $archivo) {
    $lineas = is_file($archivo) ? file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
    if ($lineas === false) {
        fwrite(STDERR, "$archivo: no se pudo leer\n");
        exit(1);
    }
    $lista = [];
    foreach ($lineas as $linea) {
        $p = json_decode($linea, true);
        if (is_array($p)) {
            $lista[] = $p;
        }
    }
    $listas[] = $lista;
}

$r = Cruce::candidatos($listas[0], $listas[1], $idioma);
foreach ($r['candidatos'] as $c) {
    echo json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
}
fwrite(STDERR, count($r['candidatos']) . " candidatos; {$r['excluidos_vivos']} perfiles omitidos por poder estar vivos\n");
