<?php

declare(strict_types=1);

// Uso: php bin/cruzar.php a.jsonl b.jsonl [--idioma=spa] [--excluir=lista.txt]
//                         [--umbral=0.6] [--anios=5] [--palabras=2]
// Propone parejas de personas FALLECIDAS que podrían ser la misma, de dos archivos JSONL
// (la salida de analizar.php --jsonl o de wikidata.php). No fusiona nada. Los perfiles que
// pueden estar vivos se omiten, y se cuenta cuántos.
//
// Lee b.jsonl para armar un índice compacto (solo lo necesario para comparar) y a.jsonl línea por
// línea, sin cargarlo entero: pon como `b` el archivo más chico.
//   --umbral    puntaje mínimo para ser candidata (0 a 1; por defecto 0.6)
//   --anios     diferencia máxima entre nacimientos, en años (por defecto 5)
//   --palabras  palabras del nombre que deben coincidir (por defecto 2)

require __DIR__ . '/../src/cargar.php';

use DefamatoryContentReview\Phonetic\PhoneticFolderRegistry;
use ParsingFbInMas\Cruce;
use ParsingFbInMas\Exclusiones;
use ParsingFbInMas\Lote;
use ParsingFbInMas\OpcionesCruce;

/**
 * Los perfiles de un JSONL, de uno en uno. Quita a los de la lista de exclusión y cuenta cuántos quitó.
 *
 * @param array{quitados: int} $cuenta
 * @return Generator<int, array<string, mixed>>
 */
function leerPerfiles(string $ruta, Exclusiones $exclusiones, array &$cuenta): Generator
{
    $f = is_file($ruta) ? @fopen($ruta, 'rb') : false;
    if ($f === false) {
        throw new RuntimeException("$ruta: no se pudo leer");
    }
    try {
        while (($linea = fgets($f)) !== false) {
            $p = json_decode(rtrim($linea, "\r\n"), true);
            if (!is_array($p) || array_is_list($p)) {
                continue;
            }
            // Un objeto JSON: se descartan las claves numéricas, que no son de un perfil.
            $perfil = array_filter($p, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($exclusiones->contiene($perfil)) {
                $cuenta['quitados']++;
                continue;
            }
            yield $perfil;
        }
    } finally {
        fclose($f);
    }
}

$archivos = [];
$idioma = 'spa';
$exclusiones = Exclusiones::vacia();
$umbral = 0.6;
$anios = 5;
$palabras = 2;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--idioma=')) {
        $idioma = substr($arg, 9);
    } elseif (str_starts_with($arg, '--umbral=')) {
        $umbral = (float) substr($arg, 9);
    } elseif (str_starts_with($arg, '--anios=')) {
        $anios = (int) substr($arg, 8);
    } elseif (str_starts_with($arg, '--palabras=')) {
        $palabras = (int) substr($arg, 11);
    } elseif (str_starts_with($arg, '--excluir=')) {
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
if (count($archivos) !== 2 || !PhoneticFolderRegistry::isSupported($idioma)) {
    fwrite(STDERR, "Uso: php bin/cruzar.php a.jsonl b.jsonl [--idioma=spa] [--excluir=lista.txt] [--umbral=0.6] [--anios=5] [--palabras=2]\n");
    exit(2);
}
try {
    $opciones = new OpcionesCruce($umbral, $anios, $palabras);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(2);
}

$cuentaA = ['quitados' => 0];
$cuentaB = ['quitados' => 0];
try {
    $indice = Cruce::indexar(leerPerfiles($archivos[1], $exclusiones, $cuentaB), $idioma);
    $omitidosA = 0;
    $candidatos = Cruce::cruzar(leerPerfiles($archivos[0], $exclusiones, $cuentaA), $indice, $idioma, $opciones, $omitidosA);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

foreach ($candidatos as $c) {
    echo json_encode($c, Lote::JSON), "\n";
}
foreach ([[$archivos[0], $cuentaA], [$archivos[1], $cuentaB]] as [$archivo, $cuenta]) {
    if ($cuenta['quitados'] > 0) {
        fwrite(STDERR, "$archivo: {$cuenta['quitados']} perfiles omitidos por la lista de exclusión\n");
    }
}
fwrite(STDERR, count($candidatos) . " candidatos; " . ($indice->omitidos + $omitidosA) . " perfiles omitidos por poder estar vivos\n");
