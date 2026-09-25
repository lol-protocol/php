<?php

// 7. Estado de los diccionarios.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');
$registry = $reviewer->languages()->registry();
$pad = fn(string $s, int $width) => $s . str_repeat(' ', max(0, $width - mb_strlen($s)));

printf("%-5s %-20s %-15s %8s %12s\n", 'CÓD', 'IDIOMA', 'COBERTURA', 'TÉRMINOS', 'COLISIONES');
echo str_repeat('─', 72) . "\n";

$total = 0;
foreach ($registry->getAll() as $code => $meta) {
    $stats = $reviewer->languages()->statistics($code);
    $total += $stats['totalWords'];

    printf(
        "%-5s %s %-15s %8d %12d\n",
        $code,
        $pad($meta['name'], 20),
        $stats['coverage'],
        $stats['totalWords'],
        $stats['nameCollisions']
    );
}

echo str_repeat('─', 72) . "\n";
printf("%d términos en %d idiomas\n", $total, count($registry->getCodes()));

$pendingReview = $reviewer->languages()->byCoverage('moderate');
if ($pendingReview) {
    printf("\nPendientes de revisión por hablante nativo: %s\n", implode(', ', $pendingReview));
}
