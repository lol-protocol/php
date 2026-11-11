<?php

declare(strict_types=1);

// Uso: php bin/analizar.php [--capturado=AAAA-MM-DD] [--jsonl] [--excluir=lista.txt] [--sin-revision] pagina.html|carpeta …
// Lee páginas YA GUARDADAS en disco (una carpeta se recorre entera; los .json, que son respuestas de la API
// de WikiTree, hay que pasarlos uno a uno) y escribe un JSON, o un
// objeto por línea con --jsonl. No descarga nada, a propósito. Una misma URL sale una vez.

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Detector;
use ParsingFbInMas\Exclusiones;
use ParsingFbInMas\Lote;
use ParsingFbInMas\WikiTreeApi;

$capturado = null;
$jsonl = false;
$revisar = true;
$exclusiones = Exclusiones::vacia();
$rutas = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--capturado=')) {
        $capturado = substr($arg, 12);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $capturado) !== 1) {
            fwrite(STDERR, "--capturado pide AAAA-MM-DD\n");
            exit(2);
        }
    } elseif ($arg === '--jsonl') {
        $jsonl = true;
    } elseif ($arg === '--sin-revision') {
        $revisar = false;
    } elseif (str_starts_with($arg, '--excluir=')) {
        try {
            $exclusiones = Exclusiones::desdeArchivo(substr($arg, 10));
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(2);
        }
    } else {
        $rutas[] = $arg;
    }
}
if ($rutas === []) {
    fwrite(STDERR, "Uso: php bin/analizar.php [--capturado=AAAA-MM-DD] [--jsonl] [--excluir=lista.txt] [--sin-revision] pagina.html|carpeta …\n");
    exit(2);
}

$perfiles = [];
$fallos = 0;
foreach (Lote::archivos($rutas) as $archivo) {
    if (Lote::esUrl($archivo)) {
        fwrite(STDERR, "$archivo: esto no descarga páginas; guarda el HTML y pásame el archivo.\n");
        $fallos++;
        continue;
    }
    $html = is_file($archivo) ? file_get_contents($archivo) : false;
    if ($html === false) {
        fwrite(STDERR, "$archivo: no se pudo leer\n");
        $fallos++;
        continue;
    }
    try {
        if (str_ends_with(strtolower($archivo), '.json')) {
            foreach (WikiTreeApi::desdeJson($html, $capturado) as $p) {
                $perfiles[] = ['archivo' => basename($archivo)] + $p->aArreglo();
            }
            continue;
        }
        $perfiles[] = ['archivo' => basename($archivo)] + Detector::analizar($html, $capturado, $revisar)->aArreglo();
    } catch (RuntimeException $e) {
        fwrite(STDERR, "$archivo: {$e->getMessage()}\n");
        $fallos++;
    }
}

['perfiles' => $perfiles, 'excluidos' => $excluidos] = $exclusiones->filtrar($perfiles);
if ($excluidos > 0) {
    fwrite(STDERR, "$excluidos perfiles omitidos por la lista de exclusión\n");
}
['perfiles' => $perfiles, 'repetidos' => $repetidos] = Lote::sinRepetidos($perfiles);
if ($repetidos > 0) {
    fwrite(STDERR, "$repetidos páginas repetidas (misma URL) omitidas\n");
}
if ($jsonl) {
    foreach ($perfiles as $p) {
        echo json_encode($p, Lote::JSON), "\n";
    }
} else {
    echo json_encode($perfiles, Lote::JSON | JSON_PRETTY_PRINT), "\n";
}
exit($fallos > 0 ? 1 : 0);
