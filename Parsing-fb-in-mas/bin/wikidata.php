<?php

declare(strict_types=1);

// Uso: php bin/wikidata.php volcado.json[.gz|.bz2] [--pais=Q298] [--max=N] [--capturado=AAAA-MM-DD] [--excluir=lista.txt] [--progreso]
// Escribe un perfil JSON por línea (JSONL) de cada persona que consta como fallecida.
// Lee un volcado local de Wikidata (CC0) en streaming: no descarga nada.

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Cli;
use ParsingFbInMas\Exclusiones;
use ParsingFbInMas\Lote;
use ParsingFbInMas\Perfil;
use ParsingFbInMas\Wikidata;

$ruta = null;
$pais = null;
$maximo = null;
$capturado = null;
$progreso = false;
$exclusiones = Exclusiones::vacia();
$uso = "Uso: php bin/wikidata.php volcado.json[.gz|.bz2] [--pais=Q298] [--max=N] [--capturado=AAAA-MM-DD] [--excluir=lista.txt] [--progreso]\nEsto no descarga el volcado: bájalo tú.\n";
Cli::validar(array_slice($argv, 1), ['--pais=', '--max=', '--capturado=', '--progreso', '--excluir='], $uso);

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--pais=')) {
        $pais = substr($arg, 7);
        if (preg_match('/^Q\d+$/', $pais) !== 1) {
            fwrite(STDERR, "--pais pide un identificador de Wikidata, como Q298 (Chile)\n");
            exit(2);
        }
    } elseif (str_starts_with($arg, '--max=')) {
        $maximo = Cli::entero('--max', substr($arg, 6), 1);
    } elseif (str_starts_with($arg, '--capturado=')) {
        $capturado = Cli::fecha('--capturado', substr($arg, 12));
    } elseif ($arg === '--progreso') {
        $progreso = true;
    } elseif (str_starts_with($arg, '--excluir=')) {
        $exclusiones = Cli::exclusiones(substr($arg, 10));
    } else {
        $ruta = $arg;
    }
}
if ($ruta === null || Lote::esUrl($ruta)) {
    fwrite(STDERR, $uso);
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
    }, $pais, $maximo, $capturado, $progreso ? static function (int $lineas, int $aceptadas): void {
        fwrite(STDERR, number_format($lineas) . " líneas leídas, $aceptadas personas\n");
    } : null);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
fwrite(STDERR, "{$r['lineas']} líneas, {$r['leidas']} con fecha de muerte, {$r['decodificadas']} decodificadas" . ($r['corruptas'] > 0 ? " ({$r['corruptas']} corruptas, saltadas)" : '') . ", {$r['aceptadas']} personas, " . ($r['aceptadas'] - $excluidos) . " escritas ($excluidos excluidas)\n");
