<?php

// Compara las formas que genera TopicInflector con las de UniMorph (https://unimorph.github.io) para palabras
// corrientes: dice, por idioma, qué parte de lo generado existe (precisión) y qué parte de lo que existe se genera
// (cobertura). No sustituye a un hablante nativo para lo ofensivo, pero sí para la morfología: un fallo sistemático
// («тъжен» → «тъжна», no «тъжена») se ve aquí. Los datos de UniMorph no están en el repositorio: descárgalos de
// https://github.com/unimorph/<código>.
//
// Uso: php bin/inflection-check.php <código> <unimorph.tsv> [lista de frecuencias] [lemas por tipo] [--ends]
//   <código>  ISO 639-3 de config/ (nor, aunque UniMorph lo llame «nob»)
//   [lista]   una palabra por línea (hermitdave/FrequencyWords): solo se prueban lemas entre las 50.000 más usadas
//   --ends    precisión por última letra del lema, para ver qué clase falla

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\Chat\Inflection\TopicInflector;

$args = array_values(array_filter(array_slice($argv, 1), fn(string $a): bool => $a !== '--ends'));
[$language, $file] = [$args[0] ?? '', $args[1] ?? ''];
if ($language === '' || !is_file($file)) {
    fwrite(STDERR, "Uso: php bin/inflection-check.php <código> <unimorph.tsv> [lista de frecuencias] [lemas por tipo] [--ends]\n");
    exit(2);
}
$common = [];
if (isset($args[2]) && is_file($args[2])) {
    foreach (new SplFileObject($args[2]) as $i => $line) {
        if ($i >= 50000) { break; }
        $common[mb_strtolower(trim(explode(' ', (string) $line)[0]))] = true;
    }
}
$limit = (int) ($args[3] ?? 600);
$strip = in_array($language, ['ara', 'heb', 'ell'], true); // UniMorph trae vocales y tildes que el diccionario pliega
$norm = fn(string $w): string => $strip ? (string) preg_replace('/\p{Mn}/u', '', (string) \Normalizer::normalize(mb_strtolower($w), \Normalizer::FORM_D)) : mb_strtolower($w);

$data = ['N' => [], 'ADJ' => []];
foreach (new SplFileObject($file) as $line) {
    [$lemma, $form, $tags] = array_pad(explode("\t", rtrim((string) $line, "\r\n")), 3, '');
    $pos = explode(';', $tags)[0];
    if (isset($data[$pos]) && !str_contains($lemma, ' ') && ($common === [] || isset($common[mb_strtolower($lemma)]))) {
        $data[$pos][$lemma][$norm($form)] = true;
    }
}
mt_srand(7);
$byEnd = [];
foreach (['N' => 'noun', 'ADJ' => 'adj'] as $pos => $kind) {
    $lemmas = array_keys($data[$pos]);
    sort($lemmas);
    shuffle($lemmas);
    $t = array_fill_keys(['n', 'generated', 'hit', 'attested', 'none'], 0);
    foreach (array_slice($lemmas, 0, $limit) as $lemma) {
        $attested = array_diff(array_keys($data[$pos][$lemma]), [$norm($lemma)]);
        $generated = array_diff(array_unique(array_map($norm, TopicInflector::forms($language, $lemma, $kind))), [$norm($lemma)]);
        $hit = count(array_intersect($generated, $attested));
        $end = mb_substr($lemma, -1);
        $byEnd[$pos][$end] = [($byEnd[$pos][$end][0] ?? 0) + 1, ($byEnd[$pos][$end][1] ?? 0) + count($generated), ($byEnd[$pos][$end][2] ?? 0) + $hit];
        foreach (['n' => 1, 'generated' => count($generated), 'hit' => $hit, 'attested' => count($attested), 'none' => (int) ($generated === [])] as $k => $v) { $t[$k] += $v; }
    }
    if ($t['n'] > 0) {
        printf("%s %-3s lemas=%d formas/lema=%.1f precisión=%.0f%% cobertura=%.0f%% sin reglas=%d%%\n", $language, $pos, $t['n'], $t['generated'] / $t['n'],
            $t['generated'] ? 100 * $t['hit'] / $t['generated'] : 0, $t['attested'] ? 100 * $t['hit'] / $t['attested'] : 0, 100 * $t['none'] / $t['n']);
    }
}
foreach (in_array('--ends', $argv, true) ? $byEnd : [] as $pos => $ends) {
    uasort($ends, fn(array $a, array $b): int => $b[0] <=> $a[0]);
    foreach (array_slice($ends, 0, 12, true) as $end => [$n, $generated, $hit]) {
        printf("   %s -%s n=%d precisión=%.0f%%\n", $pos, $end, $n, $generated ? 100 * $hit / $generated : 0);
    }
}
