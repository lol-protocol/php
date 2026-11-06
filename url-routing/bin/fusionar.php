<?php

declare(strict_types=1);

/**
 * Merges a duplicate persona (see FusionRepository) or grupo (FusionGrupoRepository) into the one that stays:
 *
 *   php bin/fusionar.php <duplicado> <vigente>                    # a persona; shows what would move
 *   php bin/fusionar.php <duplicado> <vigente> --confirmar        # does it
 *   php bin/fusionar.php grupo <duplicado> <vigente> [--confirmar]   # the same, for a surname group
 *
 * The duplicate's id becomes an alias: its URLs redirect to the one that stays.
 * The database comes from DB_DSN_GENEALOGY (or .env), as for the web app.
 */

use App\Repositories\Genealogy\FusionGrupoRepository;
use App\Repositories\Genealogy\FusionRepository;
use App\Repositories\Genealogy\GrupoRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Support\Config;
use App\Support\Database;
use App\Support\Privacidad;

require __DIR__ . '/../vendor/autoload.php';

Config::load();

$argumentos = array_values(array_filter(array_slice($argv, 1), static fn(string $a): bool => $a !== '--confirmar'));
$tipo = 'persona';
if (in_array($argumentos[0] ?? '', ['persona', 'grupo'], true)) {
    $tipo = array_shift($argumentos);
}
[$duplicado, $vigente] = $argumentos + ['', ''];
if (!ctype_digit($duplicado) || !ctype_digit($vigente) || count($argumentos) !== 2) {
    fwrite(STDERR, "Uso: php bin/fusionar.php [persona|grupo] <id duplicado> <id que queda> [--confirmar]\n");
    exit(2);
}

$db = Database::forSite('genealogy');
$privacidad = Privacidad::propietario();
$etiqueta = static function (int $id) use ($tipo, $db, $privacidad): ?string {
    if ($tipo === 'grupo') {
        $grupo = (new GrupoRepository($db, $privacidad))->find($id);

        return $grupo === null ? null : (string)$grupo['apellido'];
    }
    $persona = (new PersonaRepository($db, $privacidad))->find($id);

    return $persona === null ? null : "{$persona['nombres']} {$persona['apellidos']}";
};
foreach (['Duplicado (desaparece)' => $duplicado, 'Queda' => $vigente] as $que => $id) {
    $nombre = $etiqueta((int)$id);
    if ($nombre === null) {
        fwrite(STDERR, 'No existe ' . ($tipo === 'grupo' ? 'el grupo' : 'la persona') . " {$id}.\n");
        exit(1);
    }
    echo "{$que}: {$id} {$nombre}\n";
}

$fusion = $tipo === 'grupo' ? new FusionGrupoRepository($db) : new FusionRepository($db);
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
