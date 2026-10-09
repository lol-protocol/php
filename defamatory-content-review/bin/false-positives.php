<?php

// Palabras frecuentes de un idioma que el chat censuraría (decisión review o reject).
// Las más frecuentes son las candidatas más probables a falso positivo: un insulto
// rara vez está entre las palabras más usadas de un idioma, una palabra cotidiana sí.
//
// Uso: php bin/false-positives.php <código> <lista> [N]
//   <código>  ISO 639-3 (spa, rus, tur…)
//   <lista>   una palabra por línea, opcionalmente seguida de su frecuencia, de la más
//             a la menos usada (formato de github.com/hermitdave/FrequencyWords).
//   [N]       cuántas palabras leer (50000 por defecto).
//
// Salida: rango, palabra, decisión, tipos de contenido y el término que la marcó, en CSV.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\ChatLineReviewer;

[$language, $list] = [$argv[1] ?? '', $argv[2] ?? ''];
if ($language === '' || !is_file($list)) {
    fwrite(STDERR, "Uso: php bin/false-positives.php <código> <lista> [N]\n");
    exit(2);
}
$limit = max(1, (int) ($argv[3] ?? 50000));
$chat = ChatLineReviewer::create(__DIR__ . '/../config', $language);
$out = fopen('php://stdout', 'w');
fputcsv($out, ['rango', 'palabra', 'decision', 'tipos', 'termino']);
$rank = 0;
$flagged = 0;

foreach (new SplFileObject($list) as $line) {
    $word = trim(explode(' ', trim((string) $line))[0] ?? '');
    if ($word === '') {
        continue;
    }
    if (++$rank > $limit) {
        break;
    }
    $result = $chat->review($word);
    if ($result->shouldCensor()) {
        $terms = array_unique(array_map(fn(array $m): string => (string) $m['original'], $result->getMatches()));
        fputcsv($out, [$rank, $word, $result->getDecision(), implode(' ', $result->getContentTypes()), implode(' ', $terms)]);
        $flagged++;
    }
}

fwrite(STDERR, sprintf("%s: %d de %d palabras frecuentes se censurarían\n", $language, $flagged, min($rank, $limit)));
