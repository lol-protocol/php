<?php

declare(strict_types=1);

// Uso: php bin/wikidata-nombres.php volcado.json[.gz] perfiles.jsonl [--excluir=lista.txt]
// Segundo recorrido del volcado: pone nombre a los lugares y parientes de los perfiles que escribió
// wikidata.php (que los deja como identificadores, p. ej. Q2887). Escribe los perfiles en JSONL.
// El nombre de un pariente solo se resuelve si el volcado lo da por fallecido.

require __DIR__ . '/../src/cargar.php';

use ParsingFbInMas\Cli;
use ParsingFbInMas\Lote;
use ParsingFbInMas\Wikidata;

$archivos = [];
$exclusiones = null;
$uso = "Uso: php bin/wikidata-nombres.php volcado.json[.gz] perfiles.jsonl [--excluir=lista.txt]\nEsto no descarga el volcado: bájalo tú.\n";
Cli::validar(array_slice($argv, 1), ['--excluir='], $uso);

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--excluir=')) {
        $exclusiones = Cli::exclusiones(substr($arg, 10));
    } else {
        $archivos[] = $arg;
    }
}
if (count($archivos) !== 2 || Lote::esUrl($archivos[0]) || Lote::esUrl($archivos[1])) {
    fwrite(STDERR, $uso);
    exit(2);
}

if (!is_file($archivos[1]) || ($entrada = fopen($archivos[1], 'rb')) === false) {
    fwrite(STDERR, "{$archivos[1]}: no se pudo leer\n");
    exit(1);
}

/** Lee los perfiles de uno en uno: en memoria solo hay un perfil y los identificadores pedidos. */
$perfiles = static function () use ($entrada): Generator {
    rewind($entrada);
    while (($linea = fgets($entrada)) !== false) {
        $p = json_decode($linea, true);
        if (is_array($p)) {
            yield array_filter($p, 'is_string', ARRAY_FILTER_USE_KEY);
        }
    }
};

try {
    $quiero = Wikidata::identificadoresPedidos($perfiles());
    $datos = Wikidata::leerNombres($archivos[0], $quiero);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
$total = 0;
foreach ($perfiles() as $p) {
    echo json_encode(Wikidata::completarNombres($p, $datos, $exclusiones), Lote::JSON), "\n";
    $total++;
}
fclose($entrada);
$pendientes = count(array_diff(array_keys($quiero), array_keys($datos)));
fwrite(STDERR, $total . ' perfiles, ' . Wikidata::contarResueltos($datos) . " nombres resueltos, {$pendientes} identificadores no están en el volcado\n");
