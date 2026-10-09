<?php

// Planilla de revisión de un idioma para un hablante nativo, en CSV:
// - «término»: cada palabra del diccionario (config/languages) y de los temas de
//   chat (config/chat-topics), con su categoría, riskType, severidad y marcas;
// - «patrón»: las frases con forma de los temas de chat;
// - «excepción»: las palabras de `everyday` y `legit` de los temas de chat;
// - «frecuente»: las palabras frecuentes que el chat censuraría y que no son un
//   término de la lista, las candidatas más probables a falso positivo (ver
//   bin/false-positives.php).
// El revisor llena «correcto» (sí, no, u otra severidad) y «comentario».
//
// Uso: php bin/review-sheets.php <código> [lista de frecuencias] [N] > review/<código>.csv
//   La lista, opcional, tiene una palabra por línea de la más a la menos usada
//   (formato de github.com/hermitdave/FrequencyWords); sin ella no hay rangos
//   ni sección «frecuente». N: cuántas palabras leer (50000 por defecto).

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\ChatLineReviewer;

[$language, $list] = [$argv[1] ?? '', $argv[2] ?? ''];
$config = __DIR__ . '/../config';
if (!is_file("$config/languages/$language.php") || ($list !== '' && !is_file($list))) {
    fwrite(STDERR, "Uso: php bin/review-sheets.php <código> [lista de frecuencias] [N]\n");
    exit(2);
}
$chat = ChatLineReviewer::create($config, $language);
[$ranks, $rank, $limit] = [[], 0, max(1, (int) ($argv[3] ?? 50000))];
foreach ($list === '' ? [] : new SplFileObject($list) as $line) {
    $word = mb_strtolower(trim(explode(' ', trim((string) $line))[0] ?? ''));
    if ($word !== '' && ++$rank <= $limit) {
        $ranks[$word] ??= $rank;
    }
}

$out = fopen('php://stdout', 'w');
fputcsv($out, ['seccion', 'termino', 'origen', 'categoria', 'riskType', 'severidad', 'marcas', 'rango', 'decision_chat', 'correcto', 'comentario']);
$sources = ['diccionario' => "$config/languages/$language.php", 'temas' => "$config/chat-topics/$language.php"];
[$topics, $listed] = [[], []];
foreach ($sources as $source => $file) {
    $data = is_file($file) ? require $file : [];
    $topics = $source === 'temas' ? $data : $topics;
    foreach ($data['words'] ?? [] as $category => $entries) {
        foreach ($entries as $entry) {
            $marks = array_keys(array_filter(array_intersect_key($entry, array_flip(['nameCollision', 'ambiguous']))));
            if (isset($entry['forms'])) {
                $marks[] = 'forms:' . $entry['forms'];
            }
            $word = $entry['word'];
            $listed[mb_strtolower($word)] = true;
            fputcsv($out, ['término', $word, $source, $category, $entry['riskType'], $entry['severity'],
                implode(' ', $marks), $ranks[mb_strtolower($word)] ?? '', $chat->review($word)->getDecision(), '', '']);
        }
    }
}
foreach ($topics['patterns'] ?? [] as $pattern) {
    fputcsv($out, ['patrón', $pattern['label'] ?? '', $pattern['pattern'], 'patterns', $pattern['riskType'], $pattern['severity'], '', '', '', '', '']);
}
foreach (['everyday' => 'no se busca en el chat', 'legit' => 'no se lee sin letras repetidas'] as $key => $meaning) {
    foreach ($topics[$key] ?? [] as $word) {
        fputcsv($out, ['excepción', $word, 'temas', $key, '', '', $meaning, $ranks[$word] ?? '', $chat->review($word)->getDecision(), '', '']);
    }
}

$flagged = 0;
foreach ($ranks as $word => $position) {
    if (isset($listed[$word])) {
        continue;
    }
    $result = $chat->review((string) $word);
    if ($result->shouldCensor()) {
        $terms = array_unique(array_map(fn(array $m): string => (string) $m['original'], $result->getMatches()));
        fputcsv($out, ['frecuente', $word, implode(' ', $terms), implode(' ', $result->getContentTypes()), '', '', '', $position, $result->getDecision(), '', '']);
        $flagged++;
    }
}
fwrite(STDERR, sprintf("%s: %d de %d palabras frecuentes se censurarían sin ser términos\n", $language, $flagged, min($rank, $limit)));
