<?php

// 1. Validación en el idioma principal.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');

// printf cuenta bytes, no caracteres: con acentos las columnas se desalinean.
$pad = fn(string $s, int $width) => $s . str_repeat(' ', max(0, $width - mb_strlen($s)));

foreach ([['Zoila', 'Cerda'], ['Juan', 'Pérez'], ['Zurdo', 'Diestro'], ['Luis', 'Bastardo']] as [$first, $last]) {
    $report = $reviewer->getDetailedReport($reviewer->validateFullName($first, $last));

    printf("%s %s\n", $first, $last);
    printf("  severidad: %-8s decisión: %s\n", $report['severity'], $report['decision']);
    printf("  %s\n", $report['recommendation']);

    foreach ($report['riskAnalysis'] as $riskType => $analysis) {
        printf("    · %s %s [%s]\n", $pad($riskType, 14), $pad($analysis['description'], 42), $analysis['level']);
    }
    echo "\n";
}
