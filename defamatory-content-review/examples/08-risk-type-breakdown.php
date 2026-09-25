<?php

// 8. Reparto por tipo de riesgo (español).

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');

$stats = $reviewer->languages()->statistics('spa');
foreach ($stats['byRiskType'] as $riskType => $count) {
    printf("  %-14s %3d  %s\n", $riskType, $count, str_repeat('█', (int) round($count / 4)));
}
