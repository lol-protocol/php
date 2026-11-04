<?php

declare(strict_types=1);

// Uso: php bin/wikidata.php volcado.json[.gz] [--pais=Q298] [--max=N] [--capturado=AAAA-MM-DD]
// Escribe un perfil JSON por línea (JSONL) de cada persona que consta como fallecida.
// Lee un volcado local de Wikidata (CC0) en streaming: no descarga nada.

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Perfil;
use ParsingFbInMas\Wikidata;

$ruta = null;
$pais = null;
$maximo = null;
$capturado = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--pais=')) {
        $pais = substr($arg, 7);
        if (preg_match('/^Q\d+$/', $pais) !== 1) {
            fwrite(STDERR, "--pais pide un identificador de Wikidata, como Q298 (Chile)\n");
            exit(2);
        }
    } elseif (str_starts_with($arg, '--max=')) {
        $maximo = (int) substr($arg, 6);
    } elseif (str_starts_with($arg, '--capturado=')) {
        $capturado = substr($arg, 12);
    } else {
        $ruta = $arg;
    }
}
if ($ruta === null || preg_match('#^[a-z][a-z0-9+.-]*://#i', $ruta) === 1) {
    fwrite(STDERR, "Uso: php bin/wikidata.php volcado.json[.gz] [--pais=Q298] [--max=N] [--capturado=AAAA-MM-DD]\nEsto no descarga el volcado: bájalo tú.\n");
    exit(2);
}

try {
    $r = Wikidata::recorrer($ruta, static function (Perfil $p): void {
        echo json_encode($p->aArreglo(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    }, $pais, $maximo, $capturado);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
fwrite(STDERR, "{$r['leidas']} líneas con fecha de muerte revisadas, {$r['aceptadas']} personas escritas\n");
