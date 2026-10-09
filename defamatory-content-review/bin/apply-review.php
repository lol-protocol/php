<?php

// Aplica a config/ una planilla de review/ llenada por un hablante nativo (ver
// review/README.md). Sin --apply sólo muestra el plan; con --apply escribe los
// archivos. Nunca aplica nada si hay problemas (respuesta desconocida, planilla
// más vieja que config/): primero se corrigen.
//
// Uso: php bin/apply-review.php <código> <planilla.csv> [--apply]

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\Review\{ReviewApplier, ReviewSheet};

[$language, $file] = [$argv[1] ?? '', $argv[2] ?? ''];
if ($language === '' || !is_file($file)) {
    fwrite(STDERR, "Uso: php bin/apply-review.php <código> <planilla.csv> [--apply]\n");
    exit(2);
}
$csv = new SplFileObject($file);
$csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
$csv->setCsvControl(',', '"', '');
$sheet = new ReviewSheet($csv);
$plan = new ReviewApplier($sheet, __DIR__ . '/../config', $language);

$section = function (string $title, array $lines): void {
    if ($lines !== []) {
        echo "\n{$title} (" . count($lines) . "):\n  " . implode("\n  ", $lines) . "\n";
    }
};
printf("%s: %d cambios, %d sin responder.\n", $file, count($plan->done), $sheet->pending);
$section('Cambios', $plan->done);
$section('A mano (patrones, comentarios, marcas en las listas de temas)', $sheet->manual);
$section('Problemas', [...$sheet->problems, ...$plan->problems]);

if ($sheet->problems !== [] || $plan->problems !== []) {
    fwrite(STDERR, "\nNo se aplicó nada: corrige los problemas primero.\n");
    exit(1);
}
if (!in_array('--apply', $argv, true)) {
    echo "\nNada escrito. Repite con --apply para aplicarlo.\n";
    exit(0);
}
foreach ($plan->files as $path => $text) {
    file_put_contents($path, $text);
}
echo "\nEscrito. Siguiente: una línea por corrección en tests/fixtures/chat-lines-detection.php,\n"
    . "./vendor/bin/phpunit, y regenerar la planilla (bin/review-sheets.php).\n";
