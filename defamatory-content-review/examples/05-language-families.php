<?php

// 5. Familias lingüísticas.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');
$registry = $reviewer->languages()->registry();
$pad = fn(string $s, int $width) => $s . str_repeat(' ', max(0, $width - mb_strlen($s)));

foreach ($registry->getFamilies() as $family) {
    if (count($family['languages']) < 2) {
        continue;
    }
    printf("  %s %s\n", $pad($family['name'], 14), implode(', ', $family['languages']));
}

echo "\nPares más cercanos de cada familia:\n";
foreach (['spa' => 'por', 'ces' => 'slk', 'dan' => 'nor', 'rus' => 'ukr', 'deu' => 'nld'] as $a => $b) {
    printf("  %s ↔ %s   %.2f\n", $a, $b, $registry->getAffinity($a, $b));
}
