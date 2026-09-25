<?php

declare(strict_types=1);

/**
 * Applies pending migrations for a site, optionally loading the demo data.
 *
 *   php bin/migrate.php genealogy            # apply migrations
 *   php bin/migrate.php pos --seed           # apply + load demo data (dev only)
 *
 * The database comes from DB_DSN_<SITE> / DB_DSN (or .env), exactly as the
 * web app resolves it — see docs/DATABASE.md.
 */

use App\Support\Config;
use App\Support\Database;
use App\Support\Migrator;

require __DIR__ . '/../vendor/autoload.php';

Config::load();

$site = $argv[1] ?? '';
if (!in_array($site, ['genealogy', 'pos'], true)) {
    fwrite(STDERR, "Uso: php bin/migrate.php <genealogy|pos> [--seed]\n");
    exit(2);
}

$db = Database::forSite($site);
$migrator = Migrator::forSite($db, $site);

$aplicadas = $migrator->migrate();
echo $aplicadas === []
    ? "{$site} ({$db->driver()}): sin migraciones pendientes\n"
    : "{$site} ({$db->driver()}): aplicadas " . implode(', ', $aplicadas) . "\n";

if (in_array('--seed', $argv, true)) {
    $migrator->seed();
    echo "{$site}: datos de demostración cargados\n";
}
