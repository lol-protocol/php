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

// Con --jsonl cada perfil se escribe en cuanto se lee la página, sin acumular: una carpeta de decenas de
// miles de páginas no llena la memoria. Solo se guardan las claves de URL ya vistas (para no repetir) y, sin
// --jsonl, los perfiles, porque ahí la salida es un solo arreglo JSON.
$salida = [];
$vistos = [];
$fallos = 0;
$excluidos = 0;
$repetidos = 0;
$emitir = static function (array $datos) use ($exclusiones, $jsonl, &$salida, &$vistos, &$excluidos, &$repetidos): void {
    $perfil = array_filter($datos, 'is_string', ARRAY_FILTER_USE_KEY);
    if ($exclusiones->contiene($perfil)) {
        $excluidos++;
        return;
    }
    $url = $perfil['url'] ?? null;
    if (is_string($url)) {
        $clave = Lote::claveUrl($url);
        if (isset($vistos[$clave])) {
            $repetidos++;
            return;
        }
        $vistos[$clave] = true;
    }
    if ($jsonl) {
        echo json_encode($perfil, Lote::JSON), "\n";
    } else {
        $salida[] = $perfil;
    }
};

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
                $emitir(['archivo' => basename($archivo)] + $p->aArreglo());
            }
            continue;
        }
        $emitir(['archivo' => basename($archivo)] + Detector::analizar($html, $capturado, $revisar)->aArreglo());
    } catch (RuntimeException $e) {
        fwrite(STDERR, "$archivo: {$e->getMessage()}\n");
        $fallos++;
    }
}

if ($excluidos > 0) {
    fwrite(STDERR, "$excluidos perfiles omitidos por la lista de exclusión\n");
}
if ($repetidos > 0) {
    fwrite(STDERR, "$repetidos páginas repetidas (misma URL) omitidas\n");
}
if (!$jsonl) {
    echo json_encode($salida, Lote::JSON | JSON_PRETTY_PRINT), "\n";
}
exit($fallos > 0 ? 1 : 0);
