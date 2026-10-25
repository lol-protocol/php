<?php
/**
 * Compares the database filled by import_database.php with the master CSV, row by row and
 * column by column (accents included), and fails on the first difference it finds.
 *
 *   php import_database.php --all
 *   php tests/database_check.php          # run from the module folder
 *
 * Connection: DB_HOST, DB_PORT, DB_USER, DB_PASSWORD (same as the importer).
 * The same file lives in privacy-laws/tests and copyright-laws/tests; it picks its settings
 * from the name of the folder it is in.
 */

$module = basename(dirname(__DIR__));
$settings = [
    'privacy-laws' => [
        'db' => 'privacy_laws_db', 'parent' => 'countries', 'parentKey' => 'country_id',
        'laws' => 'privacy_laws', 'csv' => 'countries/privacy_laws_master.csv',
    ],
    'copyright-laws' => [
        'db' => 'copyright_laws_db', 'parent' => 'jurisdictions', 'parentKey' => 'jurisdiction_id',
        'laws' => 'copyright_laws', 'csv' => 'jurisdictions/copyright_laws_master.csv',
    ],
][$module] ?? null;
if ($settings === null) {
    fwrite(STDERR, "Unknown module folder: $module\n");
    exit(2);
}

$dateColumns = ['enactment_date', 'effective_date', 'pending_date', 'last_checked'];
$parentColumns = ['country_code', 'country_name', 'region'];

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$pdo = new PDO(
    "mysql:host=$host;port=$port;dbname={$settings['db']};charset=utf8mb4",
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$file = fopen(__DIR__ . '/../' . $settings['csv'], 'r');
$headers = fgetcsv($file);
$headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
$expected = [];
$codes = [];
while (($row = fgetcsv($file)) !== false) {
    if ($row === [null]) {
        continue;
    }
    $data = array_combine($headers, $row);
    $expected[$data['country_code'] . "\t" . $data['law_name']] = $data;
    $codes[$data['country_code']] = ['name' => $data['country_name'], 'region' => $data['region']];
}
fclose($file);

$problems = [];
$fail = function (string $message) use (&$problems) {
    $problems[] = $message;
};

// 1. The counts
$lawCount = (int) $pdo->query("SELECT COUNT(*) AS n FROM {$settings['laws']}")->fetch()['n'];
$parentCount = (int) $pdo->query("SELECT COUNT(*) AS n FROM {$settings['parent']}")->fetch()['n'];
if ($lawCount !== count($expected)) {
    $fail("{$settings['laws']}: " . count($expected) . " rows in the CSV, $lawCount in the database");
}
if ($parentCount !== count($codes)) {
    $fail("{$settings['parent']}: " . count($codes) . ' distinct codes in the CSV, ' . $parentCount . ' in the database');
}

// 2. The parent rows (code, name, region, tld)
foreach ($pdo->query("SELECT code, name, tld, region FROM {$settings['parent']}") as $row) {
    $want = $codes[$row['code']] ?? null;
    if ($want === null) {
        $fail("{$settings['parent']}: {$row['code']} is not in the CSV");
        continue;
    }
    if ($row['name'] !== $want['name']) {
        $fail("{$row['code']}: name \"{$row['name']}\" in the database, \"{$want['name']}\" in the CSV");
    }
    if ($row['region'] !== $want['region']) {
        $fail("{$row['code']}: region \"{$row['region']}\" in the database, \"{$want['region']}\" in the CSV");
    }
    if ($row['tld'] !== strtolower($row['code'])) {
        $fail("{$row['code']}: tld \"{$row['tld']}\" should be \"" . strtolower($row['code']) . '"');
    }
}

// 3. Every law, every column
$lawColumns = array_values(array_diff($headers, $parentColumns));
$select = "SELECT p.code AS country_code, l." . implode(', l.', $lawColumns)
    . " FROM {$settings['laws']} l JOIN {$settings['parent']} p ON p.{$settings['parentKey']} = l.{$settings['parentKey']}";
$seen = [];
foreach ($pdo->query($select) as $row) {
    $key = $row['country_code'] . "\t" . $row['law_name'];
    if (isset($seen[$key])) {
        $fail("{$row['country_code']} / {$row['law_name']}: the database has it more than once");
        continue;
    }
    $seen[$key] = true;
    $want = $expected[$key] ?? null;
    if ($want === null) {
        $fail("{$row['country_code']} / {$row['law_name']}: in the database but not in the CSV");
        continue;
    }
    foreach ($lawColumns as $column) {
        $have = $row[$column];
        $csv = $want[$column];
        if (in_array($column, $dateColumns, true)) {
            $csv = $csv === '' ? null : $csv;
        }
        if ($have !== $csv) {
            $show = fn ($v) => $v === null ? 'NULL' : '"' . mb_strimwidth($v, 0, 70, '…') . '"';
            $fail("{$row['country_code']} / {$row['law_name']} / $column: database " . $show($have) . ', CSV ' . $show($csv));
        }
    }
}
foreach (array_diff_key($expected, $seen) as $key => $row) {
    $fail(str_replace("\t", ' / ', $key) . ': in the CSV but not in the database');
}

if ($problems) {
    fwrite(STDERR, count($problems) . " difference(s) between the database and the CSV:\n");
    foreach (array_slice($problems, 0, 15) as $problem) {
        fwrite(STDERR, "  - $problem\n");
    }
    if (count($problems) > 15) {
        fwrite(STDERR, '  … and ' . (count($problems) - 15) . " more\n");
    }
    exit(1);
}
echo "$module: $lawCount laws in $parentCount " . $settings['parent'] . ", every column equal to the CSV\n";
