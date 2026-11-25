<?php

declare(strict_types=1);

// Uso: php bin/diagnostico.php pagina.html [--esqueleto]
// Para ajustar un analizador a una página real. Sin opciones escribe un JSON con qué analizador reconoció la
// página y qué campos le sacó (sí o no, sin valores). Con --esqueleto escribe la estructura de la página con
// las letras cambiadas por «x» y los dígitos por «9»: se puede compartir sin compartir datos de nadie.
// Lee un archivo ya guardado; no descarga nada.

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Cli;
use ParsingFbInMas\Diagnostico;
use ParsingFbInMas\Lote;

$archivo = null;
$esqueleto = false;
$uso = "Uso: php bin/diagnostico.php pagina.html [--esqueleto]\nEsto no descarga páginas: guarda el HTML y pásame el archivo.\n";
Cli::validar(array_slice($argv, 1), ['--esqueleto'], $uso);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--esqueleto') {
        $esqueleto = true;
    } else {
        $archivo = $arg;
    }
}
if ($archivo === null || Lote::esUrl($archivo)) {
    fwrite(STDERR, $uso);
    exit(2);
}
$html = is_file($archivo) ? file_get_contents($archivo) : false;
if ($html === false) {
    fwrite(STDERR, "$archivo: no se pudo leer\n");
    exit(1);
}

if ($esqueleto) {
    echo Diagnostico::esqueleto($html);
} else {
    echo json_encode(Diagnostico::informe($html), Lote::JSON | JSON_PRETTY_PRINT), "\n";
}
