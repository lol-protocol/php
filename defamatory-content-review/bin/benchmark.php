<?php

// Benchmark: nombres completos validados por segundo, por idioma, sobre el
// corpus de nombres reales de tests/fixtures. Uso: php bin/benchmark.php [N]
// (N = validaciones por idioma, 10000 por defecto). Sirve para comparar
// antes/después de un cambio de rendimiento en la misma máquina.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$iterations = max(1, (int) ($argv[1] ?? 10000));
$corpus = require __DIR__ . '/../tests/fixtures/common-names.php';
$totalTime = 0.0;
$totalCount = 0;

printf("%-5s %12s %12s\n", 'lang', 'nombres/s', 'µs/nombre');

foreach ($corpus as $lang => [$firsts, $lasts]) {
    $reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', $lang);
    $pairs = [];
    foreach ($firsts as $first) {
        foreach ($lasts as $last) {
            $pairs[] = [$first, $last];
        }
    }

    $reviewer->validateFullName(...$pairs[0]); // calienta índices y cachés
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $reviewer->validateFullName(...$pairs[$i % count($pairs)]);
    }
    $elapsed = (hrtime(true) - $start) / 1e9;

    $totalTime += $elapsed;
    $totalCount += $iterations;
    printf("%-5s %12s %12.1f\n", $lang, number_format($iterations / $elapsed), $elapsed / $iterations * 1e6);
}

printf("%-5s %12s %12.1f\n", 'total', number_format($totalCount / $totalTime), $totalTime / $totalCount * 1e6);
