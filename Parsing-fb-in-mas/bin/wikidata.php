<?php

declare(strict_types=1);

// Uso: php bin/wikidata.php volcado.json[.gz] [--pais=Q298] [--max=N] [--capturado=AAAA-MM-DD] [--excluir=lista.txt]
// Escribe un perfil JSON por línea (JSONL) de cada persona que consta como fallecida.
// Lee un volcado local de Wikidata (CC0) en streaming: no descarga nada.

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Exclusiones;
use ParsingFbInMas\Lote;
use ParsingFbInMas\Perfil;
use ParsingFbInMas\Wikidata;

$ruta = null;
$pais = null;
$maximo = null;
$capturado = null;
$exclusiones = Exclusiones::vacia();
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
    } elseif (str_starts_with($arg, '--excluir=')) {
        try {
            $exclusiones = Exclusiones::desdeArchivo(substr($arg, 10));
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(2);
        }
    } else {
        $ruta = $arg;
    }
}
if ($ruta === null || Lote::esUrl($ruta)) {
    fwrite(STDERR, "Uso: php bin/wikidata.php volcado.json[.gz] [--pais=Q298] [--max=N] [--capturado=AAAA-MM-DD]\nEsto no descarga el volcado: bájalo tú.\n");
    exit(2);
}

try {
    $excluidos = 0;
    $r = Wikidata::recorrer($ruta, static function (Perfil $p) use ($exclusiones, &$excluidos): void {
        $perfil = $p->aArreglo();
        if ($exclusiones->contiene($perfil)) {
            $excluidos++;
            return;
        }
        echo json_encode($perfil, Lote::JSON), "\n";
    }, $pais, $maximo, $capturado);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
fwrite(STDERR, "{$r['leidas']} líneas con fecha de muerte, {$r['decodificadas']} decodificadas, {$r['aceptadas']} personas, " . ($r['aceptadas'] - $excluidos) . " escritas ($excluidos excluidas)\n");
