<?php

// 2. Fusión fonética: nombre y apellido componen otra palabra al unirse.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');
$pad = fn(string $s, int $width) => $s . str_repeat(' ', max(0, $width - mb_strlen($s)));

// Ninguno de los dos campos es ofensivo por separado; el agravio vive en el
// punto de unión. El detector exige que la coincidencia CRUCE esa frontera,
// por eso "Mariano" o "Luciano" (que contienen "ano" enteros dentro de un
// único apellido) nunca se disparan.
foreach ([['Elba', 'Gina'], ['Felipe', 'Lotas'], ['Susana', 'Oria'], ['Mariano', 'Rajoy']] as [$first, $last]) {
    $result = $reviewer->validateFullName($first, $last);
    $report = $reviewer->getDetailedReport($result);

    printf("%s severidad=%-6s decisión=%s\n", $pad("{$first} {$last}", 16), $report['severity'], $report['decision']);

    foreach ($result->getPhoneticFusionTerms() as $term) {
        printf("    fusión → '%s' (%s)\n", $term['term'], $term['riskType']);
    }
}
