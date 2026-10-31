<?php

/**
 * Measures MultiLanguagePhoneDirectoryParser against the hand-checked cases in
 * tests/fixtures/parser-evaluation.json: which cases come out right, how many entries are found and
 * correct, and what is still failing, grouped by cause.
 *
 * Usage: php bin/evaluate-parser.php [--detail]
 * Exits 1 when a case behaves differently from what the set says (a known failure that now passes, or a
 * case that used to pass and now fails), the same thing ParserEvaluationTest checks.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhoneDirectory\Parser\ParserEvaluation;

$detail = in_array('--detail', array_slice($argv, 1), true);
['results' => $results, 'totals' => $totals] = ParserEvaluation::fromFile(__DIR__ . '/../tests/fixtures/parser-evaluation.json')->run();

/** @param array{tipo: string, nombre: string, calle: string, telefono: string|null} $row */
function formatRow(array $row): string
{
    return sprintf('%s | %s | %s | %s', $row['tipo'], $row['nombre'], $row['calle'], $row['telefono'] ?? '—');
}

$byCategory = [];
foreach ($results as $result) {
    $unexpected = $result['passes'] === ($result['knownFailure'] !== null);
    $status = match (true) {
        $unexpected && $result['passes'] => 'YA PASA',
        $unexpected => 'FALLA',
        $result['passes'] => 'ok',
        default => 'conocida',
    };
    if (!$result['passes']) {
        $category = $result['category'] ?? 'sin categoría';
        $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
    }

    printf("%-9s %s\n", $status, $result['id']);
    if ($detail && !$result['passes']) {
        foreach ($result['expected'] as $row) {
            echo "            esperado: " . formatRow($row) . "\n";
        }
        foreach ($result['actual'] as $row) {
            echo "            obtenido: " . formatRow($row) . "\n";
        }
        if ($result['expectedLanguage'] !== null && $result['expectedLanguage'] !== $result['detectedLanguage']) {
            echo "            idioma: esperado {$result['expectedLanguage']}, detectado {$result['detectedLanguage']}\n";
        }
        if ($result['parseErrors'] > 0) {
            echo "            errores de parsing: {$result['parseErrors']}\n";
        }
    }
}

$percent = static fn(int $part, int $whole): string => $whole === 0 ? '—' : sprintf('%.1f %%', 100 * $part / $whole);

echo "\n";
printf("Casos:     %d de %d salen bien (%s)\n", $totals['passing'], $totals['cases'], $percent($totals['passing'], $totals['cases']));
printf("Entradas:  %d correctas de %d esperadas (cobertura %s)\n", $totals['correctRows'], $totals['expectedRows'], $percent($totals['correctRows'], $totals['expectedRows']));
printf("           %d producidas, %d correctas (precisión %s)\n", $totals['producedRows'], $totals['correctRows'], $percent($totals['correctRows'], $totals['producedRows']));
printf("           %d errores de parsing\n", $totals['parseErrors']);

if ($byCategory !== []) {
    arsort($byCategory);
    echo "\nFallas por causa:\n";
    foreach ($byCategory as $category => $count) {
        printf("  %-26s %d\n", $category, $count);
    }
}

if ($totals['unexpected'] > 0) {
    fwrite(STDERR, "\n{$totals['unexpected']} caso(s) no se comportan como dice el conjunto: actualiza 'falla_conocida' en tests/fixtures/parser-evaluation.json.\n");
    exit(1);
}
