<?php

declare(strict_types=1);

// Uso: php bin/analizar.php [--capturado=AAAA-MM-DD] pagina1.html [pagina2.html …]
// Lee páginas YA GUARDADAS en disco y escribe un JSON. No descarga nada, a propósito.

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Detector;

$capturado = null;
$archivos = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--capturado=')) {
        $capturado = substr($arg, 12);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $capturado) !== 1) {
            fwrite(STDERR, "--capturado pide AAAA-MM-DD\n");
            exit(2);
        }
    } else {
        $archivos[] = $arg;
    }
}
if ($archivos === []) {
    fwrite(STDERR, "Uso: php bin/analizar.php [--capturado=AAAA-MM-DD] pagina.html …\n");
    exit(2);
}

$salida = [];
$fallos = 0;
foreach ($archivos as $archivo) {
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $archivo) === 1) {
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
        $salida[] = ['archivo' => basename($archivo)] + Detector::analizar($html, $capturado)->aArreglo();
    } catch (RuntimeException $e) {
        fwrite(STDERR, "$archivo: {$e->getMessage()}\n");
        $fallos++;
    }
}

echo json_encode($salida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
exit($fallos > 0 ? 1 : 0);
