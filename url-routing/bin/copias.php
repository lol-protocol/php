<?php

declare(strict_types=1);

/**
 * Takes the daily copy of the sites' databases and deletes the old ones (docs/DESPLIEGUE.md, section 11). Made for
 * cron, which mails whatever a job prints: it prints nothing when everything went well.
 *
 *   php bin/copias.php --destino=/var/backups/url-routing            # genealogy and pos, kept 30 days
 *   php bin/copias.php --destino=... --sitios=genealogy              # only some of them
 *   php bin/copias.php --destino=... --dias=60                       # another retention
 *   php bin/copias.php --destino=... --enviar='rsync -a {archivo} usuario@host:/copias/'
 *   php bin/copias.php --destino=... --cifrar-para=age1...,age1...   # encrypts each copy for these age public keys
 *
 * Each site is read from its own DB_DSN_{SITE} (as the web app does): sqlite: is copied with VACUUM INTO, pgsql: with
 * pg_dump -Fc (so pg_dump and pg_restore must be on the PATH of whoever runs it, with a role that can read the database).
 * The files are «{site}-YYYY-MM-DD.dump|sqlite», readable by their owner alone, checked before they take their name,
 * and a copy older than --dias days is deleted only after that site's new copy succeeded.
 *
 * --enviar runs the owner's command once per new copy to take it off the server («{archivo}» is replaced by the quoted
 * path). The same can come from the environment: COPIAS_DESTINO, COPIAS_DIAS, COPIAS_ENVIAR, COPIAS_CIFRAR_PARA.
 *
 * --cifrar-para encrypts each copy with age (it must be installed: apt install age) for the public keys it names, and the
 * copy ends in «.age». Only public keys are needed here: the server cannot decrypt its own copies, and the private key must
 * be kept somewhere else (restoring needs it: age -d -i clave.txt copia.dump.age > copia.dump). If age fails or is missing
 * the run fails and leaves no plain copy; without the option nothing is encrypted.
 *
 * Exit code: 0 everything done (and nothing printed), 1 a copy or a send failed (what failed goes to stderr, and the
 * other sites are still copied), 2 usage error.
 */

use App\Support\Config;
use App\Support\Copias;

require __DIR__ . '/../vendor/autoload.php';

Config::load();

$uso = "Uso: php bin/copias.php --destino=CARPETA [--sitios=genealogy,pos] [--dias=30] [--enviar='COMANDO {archivo}'] [--cifrar-para=age1...,age1...]\n";
$destino = (string)Config::get('COPIAS_DESTINO', '');
$dias = Config::getInt('COPIAS_DIAS', 30);
$enviar = (string)Config::get('COPIAS_ENVIAR', '');
$cifrarPara = (string)Config::get('COPIAS_CIFRAR_PARA', '');
$sitios = ['genealogy', 'pos'];
foreach (array_slice($argv, 1) as $argumento) {
    if (preg_match('/^--destino=(.+)$/s', $argumento, $m) === 1) {
        $destino = $m[1];
    } elseif (preg_match('/^--dias=(\d+)$/', $argumento, $m) === 1) {
        $dias = (int)$m[1];
    } elseif (preg_match('/^--enviar=(.+)$/s', $argumento, $m) === 1) {
        $enviar = $m[1];
    } elseif (preg_match('/^--cifrar-para=(.+)$/s', $argumento, $m) === 1) {
        $cifrarPara = $m[1];
    } elseif (preg_match('/^--sitios=([a-z0-9_,]+)$/', $argumento, $m) === 1) {
        $sitios = array_values(array_filter(explode(',', $m[1]), static fn(string $s): bool => $s !== ''));
    } else {
        fwrite(STDERR, $uso);
        exit(2);
    }
}
if ($destino === '' || $sitios === []) {
    fwrite(STDERR, $uso . "Falta la carpeta de destino (--destino o COPIAS_DESTINO).\n");
    exit(2);
}
if ($dias < 1 || $dias > 3650) {
    fwrite(STDERR, $uso . "La conservación es un número de días de 1 a 3650.\n");
    exit(2);
}
if ($enviar !== '' && !str_contains($enviar, '{archivo}')) {
    fwrite(STDERR, $uso . "El comando de --enviar tiene que llevar {archivo}.\n");
    exit(2);
}

try {
    $copias = new Copias($destino, $dias, array_values(array_filter(array_map('trim', explode(',', $cifrarPara)), static fn(string $d): bool => $d !== '')));
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, $uso . $e->getMessage() . "\n");
    exit(2);
}
$fallos = 0;
foreach ($sitios as $sitio) {
    $sufijo = strtoupper($sitio);
    // The same variables Database::forSite() reads; the default of a site with none is its SQLite file under var/.
    $dsn = (string)(getenv("DB_DSN_{$sufijo}") ?: 'sqlite:' . dirname(__DIR__) . "/var/{$sitio}.sqlite");
    try {
        $archivo = $copias->respaldar($sitio, $dsn, (string)(getenv("DB_USER_{$sufijo}") ?: ''), (string)(getenv("DB_PASSWORD_{$sufijo}") ?: ''));
        $copias->rotar($sitio);
        if ($enviar !== '') {
            $copias->enviar($enviar, $archivo);
        }
    } catch (\RuntimeException $e) {
        $fallos++;
        fwrite(STDERR, "Copia de {$sitio}: " . $e->getMessage() . "\n");
    }
}
exit($fallos === 0 ? 0 : 1);
