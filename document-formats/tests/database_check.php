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

check($count('SELECT COUNT(*) FROM paper_types') === count(readCsv('specs/paper_types.csv')),
    'one paper_types row per specs/paper_types.csv row');
check($count("SELECT weight_gsm_max FROM paper_types WHERE type_name = 'Papel roneo'") === 55,
    'Papel roneo tops out at 55 gsm');

// Nothing in technical_specifications.csv is skipped any more: every row is a catalog format.
check($count('SELECT COUNT(*) FROM technical_specifications') === count(readCsv('specs/technical_specifications.csv')),
    'one technical_specifications row per CSV row');

$products = readCsv('specs/product_specifications.csv');
check($count('SELECT COUNT(*) FROM product_specifications') === count($products),
    count($products) . ' product specifications, one per CSV row');
check($count('SELECT COUNT(*) FROM product_specifications WHERE paper_type_id IS NOT NULL')
    === count(array_filter($products, fn (array $p) => $p['paper_type'] !== '')),
    'every product that names a paper type is linked to it');
check($count("SELECT COUNT(*) FROM product_specifications ps JOIN paper_types pt USING (paper_type_id)
               WHERE ps.product_name = 'Receipt' AND pt.type_name = 'Papel térmico'") === 1,
    'Receipt is printed on Papel térmico');

check($count('SELECT COUNT(*) FROM finish_types') === count(readCsv('specs/finish_types.csv')), 'one finish_types row per CSV row');
check($count('SELECT COUNT(*) FROM paper_types WHERE finish NOT IN (SELECT finish_name FROM finish_types)') === 0,
    'every paper type uses a finish from finish_types');
check($count('SELECT COUNT(*) FROM paper_weight_standards') === count(readCsv('specs/paper_weight_standards.csv')),
    'one paper_weight_standards row per CSV row');
check($count("SELECT gsm FROM paper_weight_standards WHERE weight_name = '20 lb Bond'") === 75, '20 lb Bond is 75 gsm');

// The view keeps every product, with its paper type when it has one.
$products = readCsv('specs/product_specifications.csv');
check($count('SELECT COUNT(*) FROM v_products_with_paper') === count($products), 'v_products_with_paper lists every product');
check($count('SELECT COUNT(*) FROM v_products_with_paper WHERE paper_type IS NOT NULL') === count(array_filter($products, fn ($p) => $p['paper_type'] !== '')),
    'v_products_with_paper has a paper type for each product that names one');
check($count("SELECT COUNT(*) FROM v_products_with_paper WHERE product_name = 'Receipt' AND paper_type = 'Papel térmico' AND paper_type_en = 'Thermal paper'") === 1,
    'Receipt -> Papel térmico / Thermal paper in the view');

exit($failures === 0 ? 0 : 1);
