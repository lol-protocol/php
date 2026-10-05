<?php

/**
 * Checks a database filled by import_database.php against the CSV files.
 * Uses the same DB_DSN / DB_USER / DB_PASSWORD variables; exits 1 on the
 * first failed check.
 */

require __DIR__ . '/../import_database.php';

$db = connect();
$failures = 0;

function check(bool $ok, string $what): void
{
    global $failures;
    echo ($ok ? 'ok     ' : 'FAILED ') . $what . "\n";
    $failures += $ok ? 0 : 1;
}

$count = fn (string $sql): int => (int) $db->query($sql)->fetchColumn();

// Every catalog row is in document_formats, with its size, and nothing else is.
$expected = [];
foreach (CATALOGS as $file) {
    foreach (readCsv($file) as $row) {
        $expected[implode('|', [$row['format_name'], $row['country'], $row['width_mm'], $row['height_mm']])] = $row;
    }
}
check($count('SELECT COUNT(*) FROM document_formats') === count($expected),
    count($expected) . ' formats, one per distinct catalog row');

$find = $db->prepare(
    'SELECT COUNT(*) FROM document_formats df JOIN countries c ON c.country_id = df.country_id
     WHERE df.format_name = ? AND (c.country_name = ? OR c.country_code = ?) AND df.width_mm = ? AND df.height_mm = ?'
);
$missing = [];
foreach ($expected as $row) {
    $code = COUNTRY_ALIASES[$row['country']] ?? $row['country'];
    $find->execute([$row['format_name'], $row['country'], $code, $row['width_mm'], $row['height_mm']]);
    if ((int) $find->fetchColumn() !== 1) {
        $missing[] = "{$row['format_name']} ({$row['country']})";
    }
}
check($missing === [], 'every catalog row found with its country and size' . ($missing ? ': missing ' . implode(', ', $missing) : ''));

// The views join every row: a format without a country, category or type would drop out.
check($count('SELECT COUNT(*) FROM v_formats_detailed') === count($expected), 'v_formats_detailed lists every format');
check($count('SELECT COUNT(*) FROM v_formats_by_measurement') === count($expected), 'v_formats_by_measurement lists every format');

$matrix = readCsv('specs/format_equivalence_matrix.csv');
check($count('SELECT COUNT(*) FROM format_conversions') === count($matrix), count($matrix) . ' conversions, one per matrix row');
check($count('SELECT COUNT(*) FROM v_format_equivalents') === count($matrix), 'v_format_equivalents lists every conversion');
check($count("SELECT equivalence_percentage FROM v_format_equivalents WHERE from_format = 'A4' AND to_format = 'A5'") === 71,
    'A4 -> A5 is 71% similar');

check($count("SELECT height_mm FROM document_formats df JOIN countries c USING (country_id)
               WHERE df.format_name = 'Oficio' AND c.country_code = 'MX'") === 340, 'Mexican Oficio is 340 mm tall');
check($count('SELECT COUNT(*) FROM countries') === count(readCsv('countries/countries_200_complete.csv')) + count(REGIONS),
    'every country plus the International and Europe regions');
check($count('SELECT COUNT(*) FROM standard_references') > 0, 'standard references imported');
check($count('SELECT COUNT(*) FROM country_compatibility') === count(readCsv('countries/country_compatibility_guide.csv')),
    'one compatibility row per country in the guide');

exit($failures === 0 ? 0 : 1);
