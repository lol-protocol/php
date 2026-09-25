<?php

/**
 * Loads the CSV files into the tables of database_schema.sql (MySQL 8 /
 * MariaDB 10.6+).
 *
 *   DB_DSN='mysql:host=127.0.0.1;dbname=formats;charset=utf8mb4' \
 *   DB_USER=... DB_PASSWORD=... php import_database.php [--create-schema]
 *
 * --create-schema runs database_schema.sql first, on an empty database.
 * The import itself can be re-run: it replaces the rows it owns.
 *
 * Filled: countries, format_categories, document_formats,
 * format_conversions, technical_specifications, printing_margins,
 * country_compatibility, standard_references. No CSV feeds the supplier,
 * price, paper-weight, finish or variant tables, so they stay empty.
 */

const DATA = __DIR__;

// Every file listing sheet sizes. Same list as FORMAT_CATALOG_FILES in
// app/document-formats.js; a format named in several countries resolves to
// the first one listed here (A4 -> International, Letter -> USA).
const CATALOGS = [
    'specs/iso_216_series.csv',
    'countries/usa_formats.csv',
    'countries/mexico_formats.csv',
    'countries/europe_formats.csv',
    'countries/japan_formats.csv',
    'countries/china_formats.csv',
    'countries/asia_pacific_formats.csv',
    'countries/middle_east_africa_formats.csv',
    'countries/other_countries_formats.csv',
    'formats/book_formats.csv',
    'formats/legal_documents.csv',
    'formats/corporate_stationery_formats.csv',
    'formats/labels_stickers_formats.csv',
    'formats/photo_print_formats.csv',
    'formats/specialty_papers.csv',
];

// Names the catalogs use for places that countries_200_complete.csv spells
// differently, or that aren't countries. XI and XE are user-assigned ISO
// 3166 codes, since the schema requires a two-letter code.
const COUNTRY_ALIASES = ['USA' => 'US', 'UK' => 'GB', 'UAE' => 'AE'];
const REGIONS = [
    ['XI', 'International', 'Mixed'],
    ['XE', 'Europe', 'Metric'],
];

// The catalogs have no format type column; it follows from the category.
const TYPE_BY_CATEGORY = [
    'Envelope' => 'Envelope', 'Book' => 'Book', 'Label' => 'Label', 'Sticker' => 'Label',
    'Card' => 'Card', 'Legal' => 'Document', 'Form' => 'Document', 'Financial' => 'Document',
];

const STANDARD_BODIES = [
    'iso' => 'ISO', 'ansi' => 'ANSI', 'jis' => 'JISC', 'gb' => 'SAC', 'din' => 'DIN',
];

function readCsv(string $file): array
{
    $handle = fopen(DATA . '/' . $file, 'r');
    $header = fgetcsv($handle, null, ',', '"', '');
    $rows = [];
    while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
        if ($values === [null]) {
            continue;
        }
        $rows[] = array_combine($header, array_map('trim', $values));
    }
    fclose($handle);

    return $rows;
}

function nullIfEmpty(?string $value): ?string
{
    return $value === null || $value === '' || $value === 'N/A' ? null : $value;
}

/** "60-300" -> [60, 300]; "300" -> [300, 300]; anything else -> [null, null]. */
function rangeOf(string $value): array
{
    return preg_match('/^(\d+)(?:-(\d+))?$/', $value, $m) ? [(int) $m[1], (int) ($m[2] ?? $m[1])] : [null, null];
}

function equivalenceLevel(int $percent): string
{
    return match (true) {
        $percent >= 100 => 'Exact',
        $percent >= 95 => 'Very Similar',
        $percent >= 85 => 'Similar',
        default => 'Low',
    };
}

function connect(): PDO
{
    $dsn = getenv('DB_DSN') ?: throw new RuntimeException('Set DB_DSN, e.g. mysql:host=127.0.0.1;dbname=formats;charset=utf8mb4');

    return new PDO($dsn, getenv('DB_USER') ?: null, getenv('DB_PASSWORD') ?: null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function createSchema(PDO $db): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(DATA . '/database_schema.sql'));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $db->exec($statement);
    }
}

function import(PDO $db): array
{
    $report = ['skipped' => []];
    $db->beginTransaction();

    // Rows this script owns; deleting formats cascades to their dependents.
    $db->exec('DELETE FROM country_compatibility');
    $db->exec('DELETE FROM document_formats');

    // Countries: upsert on the ISO code, so the schema's sample rows are updated, not duplicated.
    $upsertCountry = $db->prepare(
        'INSERT INTO countries (country_code, country_name, measurement_system) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE country_name = VALUES(country_name), measurement_system = VALUES(measurement_system)'
    );
    foreach (readCsv('countries/countries_200_complete.csv') as $country) {
        $upsertCountry->execute([$country['country_code'], $country['country_name'], $country['measurement_system']]);
    }
    foreach (REGIONS as $region) {
        $upsertCountry->execute($region);
    }
    $countryIds = [];
    foreach ($db->query('SELECT country_id, country_code, country_name FROM countries') as $row) {
        $countryIds[$row['country_name']] = (int) $row['country_id'];
        $countryIds[$row['country_code']] = (int) $row['country_id'];
    }
    $countryId = fn (string $name): ?int => $countryIds[COUNTRY_ALIASES[$name] ?? $name] ?? null;
    $report['countries'] = (int) $db->query('SELECT COUNT(*) FROM countries')->fetchColumn();

    // Formats, with their categories and types.
    $insertCategory = $db->prepare('INSERT IGNORE INTO format_categories (category_name) VALUES (?)');
    $typeIds = $db->query('SELECT type_name, type_id FROM format_types')->fetchAll(PDO::FETCH_KEY_PAIR);
    $insertFormat = $db->prepare(
        'INSERT INTO document_formats (format_name, country_id, category_id, type_id, width_mm, height_mm,
             width_inches, height_inches, aspect_ratio, description, common_use)
         VALUES (?, ?, (SELECT category_id FROM format_categories WHERE category_name = ?), ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $formatIds = [];
    $seen = [];
    foreach (CATALOGS as $file) {
        foreach (readCsv($file) as $format) {
            $key = implode('|', [$format['format_name'], $format['country'], $format['width_mm'], $format['height_mm']]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $country = $countryId($format['country']) ?? throw new RuntimeException("{$file}: unknown country \"{$format['country']}\"");
            $insertCategory->execute([$format['category']]);
            $insertFormat->execute([
                $format['format_name'], $country, $format['category'],
                $typeIds[TYPE_BY_CATEGORY[$format['category']] ?? 'Paper'],
                $format['width_mm'], $format['height_mm'], $format['width_inches'], $format['height_inches'],
                $format['aspect_ratio'], nullIfEmpty($format['description']), nullIfEmpty($format['common_use']),
            ]);
            $formatIds[$format['format_name']] ??= (int) $db->lastInsertId();
        }
    }
    $report['document_formats'] = count($seen);
    $formatId = function (string $name, string $source) use (&$formatIds, &$report): ?int {
        if (!isset($formatIds[$name])) {
            $report['skipped'][] = "{$source}: no format called \"{$name}\"";
        }
        return $formatIds[$name] ?? null;
    };

    // Conversions from the equivalence matrix.
    $insertConversion = $db->prepare(
        'INSERT INTO format_conversions (from_format_id, to_format_id, equivalence_percentage, equivalence_level, conversion_notes)
         VALUES (?, ?, ?, ?, ?)'
    );
    $report['format_conversions'] = 0;
    foreach (readCsv('specs/format_equivalence_matrix.csv') as $pair) {
        $from = $formatId($pair['source_format'], 'format_equivalence_matrix');
        $to = $formatId($pair['target_format'], 'format_equivalence_matrix');
        if ($from === null || $to === null) {
            continue;
        }
        $percent = (int) $pair['similarity_percent'];
        $insertConversion->execute([$from, $to, $percent, equivalenceLevel($percent), "{$pair['compatibility_level']}: {$pair['notes']}"]);
        $report['format_conversions']++;
    }

    // Paper weight, DPI and finish per format.
    $insertSpec = $db->prepare(
        'INSERT INTO technical_specifications (format_id, weight_gsm_min, weight_gsm_max, recommended_dpi, finish_type, special_notes)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $report['technical_specifications'] = 0;
    foreach (readCsv('specs/technical_specifications.csv') as $spec) {
        if (($format = $formatId($spec['format_name'], 'technical_specifications')) === null) {
            continue;
        }
        [$gsmMin, $gsmMax] = rangeOf($spec['weight_gsm_range']);
        [$dpi] = rangeOf($spec['recommended_dpi']);
        $notes = implode('. ', array_filter([nullIfEmpty($spec['uses']), nullIfEmpty($spec['notes'])]));
        $insertSpec->execute([$format, $gsmMin, $gsmMax, $dpi, nullIfEmpty($spec['finish_type']), $notes ?: null]);
        $report['technical_specifications']++;
    }

    // Book margins; inner/outer are stored as left/right of a recto page.
    $insertMargin = $db->prepare(
        "INSERT INTO printing_margins (format_id, top_mm, bottom_mm, left_mm, right_mm, margin_type, description)
         VALUES (?, ?, ?, ?, ?, 'Print', ?)"
    );
    $report['printing_margins'] = 0;
    foreach (readCsv('formats/book_margins.csv') as $margin) {
        if (($format = $formatId($margin['book_format'], 'book_margins')) === null) {
            continue;
        }
        $insertMargin->execute([$format, $margin['top_mm'], $margin['bottom_mm'], $margin['inner_mm'], $margin['outer_mm'],
            nullIfEmpty($margin['notes'])]);
        $report['printing_margins']++;
    }

    // Per-country compatibility notes. "Carta/Oficio" lists alternatives; the first is primary.
    $insertCompatibility = $db->prepare(
        'INSERT INTO country_compatibility (country_id, primary_format_id, measurement_system, recommended_export_format, challenges, adoption_notes)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $report['country_compatibility'] = 0;
    foreach (readCsv('countries/country_compatibility_guide.csv') as $guide) {
        if (($country = $countryId($guide['country'])) === null) {
            $report['skipped'][] = "country_compatibility_guide: unknown country \"{$guide['country']}\"";
            continue;
        }
        $primary = explode('/', $guide['primary_format'])[0];
        $insertCompatibility->execute([$country, $formatIds[$primary] ?? null, $guide['measurement_system'],
            nullIfEmpty($guide['recommended_for_export']), nullIfEmpty($guide['challenges']), nullIfEmpty($guide['notes'])]);
        $report['country_compatibility']++;
    }

    // One row per standard a format belongs to (ISO 216, ANSI, JIS, GB/T, DIN 476).
    $insertReference = $db->prepare(
        'INSERT INTO standard_references (format_id, standard_name, standard_code, issuing_body, year_published)
         VALUES (?, ?, ?, ?, ?)'
    );
    $report['standard_references'] = 0;
    foreach (readCsv('specs/standards_reference.csv') as $reference) {
        if (($format = $formatId($reference['format_name'], 'standards_reference')) === null) {
            continue;
        }
        $year = ctype_digit($reference['adoption_year']) ? (int) $reference['adoption_year'] : null;
        foreach (STANDARD_BODIES as $prefix => $body) {
            if (($name = nullIfEmpty($reference["{$prefix}_standard"])) !== null) {
                $insertReference->execute([$format, $name, nullIfEmpty($reference["{$prefix}_number"]), $body, $year]);
                $report['standard_references']++;
            }
        }
    }

    $db->commit();

    return $report;
}

if (PHP_SAPI === 'cli' && realpath($argv[0]) === __FILE__) {
    $db = connect();
    if (in_array('--create-schema', $argv, true)) {
        createSchema($db);
    }
    $report = import($db);
    foreach ($report as $table => $count) {
        if ($table !== 'skipped') {
            printf("%-26s %d rows\n", $table, $count);
        }
    }
    // Rows naming something that isn't a format in the catalogs, e.g. a
    // product type ("Vinyl Banner") or a book genre ("Poetry").
    $skipped = [];
    foreach ($report['skipped'] as $reason) {
        [$source, $what] = explode(': ', $reason, 2);
        $skipped[$source][] = preg_replace('/^no format called /', '', $what);
    }
    foreach ($skipped as $source => $names) {
        printf("skipped %d rows of %s: %s\n", count($names), $source, implode(', ', $names));
    }
}
