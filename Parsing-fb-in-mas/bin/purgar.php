<?php

declare(strict_types=1);

// Uso: php bin/purgar.php entrada.jsonl --excluir=lista.txt [--en-sitio] [> salida.jsonl]
// Quita de un JSONL YA GENERADO a las personas de la lista de exclusión (las que `--excluir` solo evita al
// reprocesar). Lee y escribe línea por línea. Sin --en-sitio escribe en la salida estándar y no toca el
// archivo; con --en-sitio reemplaza el archivo (escribe uno temporal al lado y lo renombra).

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Exclusiones;
use ParsingFbInMas\Lote;
use ParsingFbInMas\Purga;

$entrada = null;
$exclusiones = null;
$enSitio = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--excluir=')) {
        try {
            $exclusiones = Exclusiones::desdeArchivo(substr($arg, 10));
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(2);
        }
    } elseif ($arg === '--en-sitio') {
        $enSitio = true;
    } else {
        $entrada = $arg;
    }
}
if ($entrada === null || $exclusiones === null || Lote::esUrl($entrada)) {
    fwrite(STDERR, "Uso: php bin/purgar.php entrada.jsonl --excluir=lista.txt [--en-sitio] [> salida.jsonl]\n");
    exit(2);
}
$origen = is_file($entrada) ? @fopen($entrada, 'rb') : false;
if ($origen === false) {
    fwrite(STDERR, "$entrada: no se pudo leer\n");
    exit(1);
}

$temporal = $enSitio ? $entrada . '.purga-' . getmypid() . '.tmp' : null;
$destino = $temporal !== null ? fopen($temporal, 'wb') : STDOUT;
if ($destino === false) {
    fwrite(STDERR, "No se pudo escribir $temporal\n");
    exit(1);
}

$leidas = 0;
$quitadas = 0;
$parientes = 0;
$ilegibles = 0;
while (($linea = fgets($origen)) !== false) {
    $linea = rtrim($linea, "\r\n");
    if ($linea === '') {
        continue;
    }
    $leidas++;
    $r = Purga::linea($linea, $exclusiones);
    $ilegibles += $r['ilegible'] ? 1 : 0;
    $parientes += $r['parientes'];
    if ($r['linea'] === null) {
        $quitadas++;
        continue;
    }
    fwrite($destino, $r['linea'] . "\n");
}
fclose($origen);
if ($temporal !== null) {
    fclose($destino);
    if (!rename($temporal, $entrada)) {
        @unlink($temporal);
        fwrite(STDERR, "No se pudo reemplazar $entrada\n");
        exit(1);
    }
}

fwrite(STDERR, "$leidas líneas, $quitadas personas quitadas, $parientes parientes quitados" . ($ilegibles > 0 ? ", $ilegibles líneas ilegibles conservadas sin revisar" : '') . "\n");
exit($ilegibles > 0 ? 1 : 0);
