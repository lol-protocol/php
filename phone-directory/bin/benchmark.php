<?php

/**
 * Measures parsing, batch insert and search speed on a synthetic directory.
 *
 * Usage: php bin/benchmark.php [entries=100000]
 * Numbers quoted in docs/ESTIMATES_AND_SCALABILITY.md come from this script.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhoneDirectory\Parser\MultiLanguagePhoneDirectoryParser;
use PhoneDirectory\PhoneDirectoryPDODatabase;

$count = max(1, (int) ($argv[1] ?? 100000));
$workdir = sys_get_temp_dir() . '/phonedir_benchmark_' . getmypid();
mkdir($workdir);
$file = "{$workdir}/directory.txt";
$dbFile = "{$workdir}/benchmark.sqlite";

$firstNames = ['John', 'Mary', 'Robert', 'Ana', 'José', 'Hans', 'Marie', 'Luigi'];
$surnames = ['SMITH', 'GARCÍA', 'MÜLLER', 'ROSSI', 'DUPONT', 'JONES', 'LÓPEZ', 'BROWN'];
$streets = ['Main Street', 'Oak Avenue', 'Pine Road', 'Elm Lane', 'Maple Drive'];

$handle = fopen($file, 'w');
for ($i = 0; $i < $count; $i++) {
    fwrite($handle, sprintf(
        "%s, %s\n%d %s\n555-%03d-%04d\n\n",
        $surnames[$i % 8],
        $firstNames[intdiv($i, 8) % 8],
        $i % 900 + 1,
        $streets[$i % 5],
        $i % 1000,
        $i % 10000
    ));
}
fclose($handle);
$sizeMb = filesize($file) / 1048576;

try {
    $start = microtime(true);
    $parser = new MultiLanguagePhoneDirectoryParser();
    $entries = $parser->parseFile($file, 'en');
    $parseSeconds = microtime(true) - $start;

    printf("PHP %s, %d entries, %.1f MB\n\n", PHP_VERSION, count($entries), $sizeMb);
    printf("%-28s %8.0f entries/s  (%.2f s, %.1f MB/s)\n", 'parse', count($entries) / $parseSeconds, $parseSeconds, $sizeMb / $parseSeconds);
    printf("%-28s %8.0f MB\n", 'peak memory after parsing', memory_get_peak_usage(true) / 1048576);

    $database = new PhoneDirectoryPDODatabase("sqlite:{$dbFile}");
    $database->createTable();
    $people = array_map(fn(array $item) => $item['entity'], $entries);

    $start = microtime(true);
    $inserted = $database->insertBatch($people);
    $insertSeconds = microtime(true) - $start;
    printf("%-28s %8.0f rows/s     (%.2f s)\n", 'insertBatch (SQLite file)', $inserted / $insertSeconds, $insertSeconds);
    printf("%-28s %8.0f bytes/row  (%.1f MB)\n\n", 'database size', filesize($dbFile) / $inserted, filesize($dbFile) / 1048576);

    $queries = [
        'findById' => fn() => $database->findById(intdiv($inserted, 2)),
        'findByPhone' => fn() => $database->findByPhone('555-000-0000'),
        'findByName (substring)' => fn() => $database->findByName('garcia'),
        'findBySurnameSound' => fn() => $database->findBySurnameSound('Smyth'),
        'findByStreet (substring)' => fn() => $database->findByStreet('Oak Avenue'),
    ];
    foreach ($queries as $name => $query) {
        $start = microtime(true);
        $result = $query();
        $rows = is_array($result) ? count($result) : (int) ($result !== null);
        printf("%-28s %8.1f ms       (%d rows)\n", $name, (microtime(true) - $start) * 1000, $rows);
    }

    $database->disconnect();
} finally {
    @unlink($file);
    @unlink($dbFile);
    @rmdir($workdir);
}
