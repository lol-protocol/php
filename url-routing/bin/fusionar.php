<?php

declare(strict_types=1);

/**
 * Merges a duplicate persona into the one that stays (see FusionRepository):
 *
 *   php bin/fusionar.php <duplicado> <vigente>               # shows what would move
 *   php bin/fusionar.php <duplicado> <vigente> --confirmar   # does it
 *
 * The duplicate's id becomes an alias: its URLs redirect to the one that stays.
 * The database comes from DB_DSN_GENEALOGY (or .env), as for the web app.
 */

use App\Repositories\Genealogy\FusionRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Support\Config;
use App\Support\Database;
use App\Support\Privacidad;

require __DIR__ . '/../vendor/autoload.php';

Config::load();

[, $duplicado, $vigente] = $argv + [null, '', ''];
if (!ctype_digit($duplicado) || !ctype_digit($vigente)) {
    fwrite(STDERR, "Uso: php bin/fusionar.php <id duplicado> <id que queda> [--confirmar]\n");
    exit(2);
}

$db = Database::forSite('genealogy');
$personas = new PersonaRepository($db, Privacidad::propietario());
foreach (['Duplicado (desaparece)' => $duplicado, 'Queda' => $vigente] as $que => $id) {
    $persona = $personas->find((int)$id);
    if ($persona === null) {
        fwrite(STDERR, "No existe la persona {$id}.\n");
        exit(1);
    }
    echo "{$que}: {$id} {$persona['nombres']} {$persona['apellidos']}\n";
}

$fusion = new FusionRepository($db);
echo "Se mueve a {$vigente}:\n";
foreach ($fusion->resumen((int)$duplicado) as $que => $cuantas) {
    echo "  {$que}: {$cuantas}\n";
}

if (!in_array('--confirmar', $argv, true)) {
    echo "Nada cambió. Para fusionar, repetir con --confirmar.\n";
    exit(0);
}

try {
    $fusion->fusionar((int)$duplicado, (int)$vigente);
} catch (\DomainException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "Fusionadas: /{$duplicado}/ redirige ahora a /{$vigente}/.\n";
