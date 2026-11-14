<?php

declare(strict_types=1);

/**
 * The solicitudes of access, correction or erasure that anyone makes about a persona at /s/{id}/ (see
 * SolicitudRepository and docs/SOLICITUDES.md). Receiving one gives, changes and erases nothing: the owner reads it,
 * checks who asks and answers by hand.
 *
 *   php bin/solicitudes.php                     # the ones waiting: number, persona, right and how long (nothing if none)
 *   php bin/solicitudes.php --ver=N             # one in full, with the e-mail and the text of whoever asked
 *   php bin/solicitudes.php --atender=N [--nota=TEXTO]    # closes it as answered
 *   php bin/solicitudes.php --rechazar=N [--nota=TEXTO]   # closes it as refused (who asks could not be checked, ...)
 *   php bin/solicitudes.php --depurar [--meses=12]        # erases the e-mail and the text of the ones closed long ago, and the deliveries with a key that expired long ago
 *   php bin/solicitudes.php --solo-nuevas       # tell about the solicitudes that arrived since the last run, and nothing else
 *
 * The listing is for cron (it mails whatever a job prints, and it prints nothing when nothing waits) and, like
 * bin/pedidos-retiro.php, never says who asked nor what they wrote: cron mail travels in the clear. Only --ver does,
 * on the terminal of the owner. The note of a closing is for the owner's records: say what was done, not the data.
 * --depurar prints only a count of each. The retention is --meses, else SOLICITUD_CONSERVAR_MESES, else 12. The deliveries
 * with a key (persona.php entrega) hold only hashes and dates; the ones that expired that long ago are deleted outright.
 *
 * --solo-nuevas is for a job that runs every few minutes: it prints (number, persona and right, never who asked nor what they
 * wrote) only when a solicitud arrived that it has not announced before, and is silent otherwise. Each one is announced once
 * (solicitudes_persona.avisada_en): it is the notice that something just came in, where the listing is the reminder of what
 * still waits. It goes alone: no other option, and exit code 0.
 *
 * Exit code: 0 done (or nothing to say), 1 there is no such solicitud or it was closed already, 2 usage error or a
 * database that is not ready.
 */

use App\Repositories\Genealogy\EntregaRepository;
use App\Repositories\Genealogy\SolicitudRepository;
use App\Support\Config;
use App\Support\Database;

require __DIR__ . '/../vendor/autoload.php';

Config::load();

$uso = "Uso: php bin/solicitudes.php [--ver=N | --atender=N [--nota=TEXTO] | --rechazar=N [--nota=TEXTO] | --depurar [--meses=12] | --solo-nuevas]\n";
$accion = null;
$numero = null;
$nota = null;
$meses = Config::getInt('SOLICITUD_CONSERVAR_MESES', SolicitudRepository::CONSERVAR_MESES);
foreach (array_slice($argv, 1) as $argumento) {
    if (preg_match('/^--(ver|atender|rechazar)=(\d+)$/', $argumento, $m) === 1 && $accion === null) {
        [$accion, $numero] = [$m[1], (int)$m[2]];
    } elseif ($argumento === '--depurar' && $accion === null) {
        $accion = 'depurar';
    } elseif ($argumento === '--solo-nuevas' && $accion === null) {
        $accion = 'nuevas';
    } elseif (preg_match('/^--nota=(.+)$/s', $argumento, $m) === 1) {
        $nota = trim($m[1]);
    } elseif (preg_match('/^--meses=(\d+)$/', $argumento, $m) === 1) {
        $meses = (int)$m[1];
    } else {
        fwrite(STDERR, $uso);
        exit(2);
    }
}
if (($nota !== null && !in_array($accion, ['atender', 'rechazar'], true)) || $meses < 1 || $meses > 240) {
    fwrite(STDERR, $uso . "--nota va con --atender o --rechazar; la conservación es un número de meses de 1 a 240.\n");
    exit(2);
}
if ($accion === 'nuevas' && count($argv) > 2) {
    fwrite(STDERR, $uso . "--solo-nuevas va sola: es el aviso de lo que llegó, sin listado, cierre ni depuración.\n");
    exit(2);
}
if ($accion === null && count($argv) > 1) {
    fwrite(STDERR, $uso);
    exit(2);
}

try {
    $db = Database::forSite('genealogy');
    $repo = new SolicitudRepository($db);

    if ($accion === 'ver') {
        $s = $repo->ver((int)$numero);
        if ($s === null) {
            fwrite(STDERR, "No existe la solicitud {$numero}.\n");
            exit(1);
        }
        printf("Solicitud n.º %d — %s sobre la persona %d (%s, recibida el %s)\n", $s['id'], SolicitudRepository::DERECHOS[$s['derecho']], $s['persona_id'], $s['estado'], $s['creado_en']);
        if ($s['depurado_en'] !== null) {
            echo "Su correo y su texto se borraron el {$s['depurado_en']}.\n";
        } else {
            echo "Responder a: {$s['contacto']}\n\n{$s['detalle']}\n";
        }
        if ($s['cerrada_en'] !== null) {
            echo "\nCerrada el {$s['cerrada_en']}" . ($s['nota'] !== null && $s['nota'] !== '' ? ": {$s['nota']}" : '') . ".\n";
        }
        exit(0);
    }

    if ($accion === 'atender' || $accion === 'rechazar') {
        if (!$repo->cerrar((int)$numero, $accion, $nota)) {
            fwrite(STDERR, "No hay una solicitud pendiente {$numero}: no existe o ya estaba cerrada.\n");
            exit(1);
        }
        echo "La solicitud {$numero} quedó como «" . SolicitudRepository::CIERRES[$accion] . "».\n";
        exit(0);
    }

    if ($accion === 'nuevas') {
        $nuevas = $repo->avisarNuevas();
        if ($nuevas !== []) {
            echo count($nuevas) === 1 ? "Llegó 1 solicitud de datos:\n" : 'Llegaron ' . count($nuevas) . " solicitudes de datos:\n";
            foreach ($nuevas as $n) {
                printf("  n.º %d  %s  persona %d\n", $n['id'], $n['derecho'], $n['persona_id']);
            }
            echo "\nLéela con: php bin/solicitudes.php --ver=N (y recuerda comprobar quién pide: docs/SOLICITUDES.md).\n";
        }
        exit(0);
    }

    if ($accion === 'depurar') {
        $borradas = $repo->depurar($meses);
        if ($borradas !== []) {
            echo count($borradas) === 1
                ? "Se borraron el correo y el texto de 1 solicitud cerrada hace más de {$meses} meses.\n"
                : 'Se borraron el correo y el texto de ' . count($borradas) . " solicitudes cerradas hace más de {$meses} meses.\n";
        }
        $entregas = (new EntregaRepository($db))->depurar($meses);
        if ($entregas > 0) {
            echo $entregas === 1
                ? "Se borró 1 entrega con clave vencida hace más de {$meses} meses.\n"
                : "Se borraron {$entregas} entregas con clave vencidas hace más de {$meses} meses.\n";
        }
        exit(0);
    }

    $pendientes = $repo->pendientes();
    if ($pendientes === []) {
        exit(0);
    }
    echo count($pendientes) === 1 ? "1 solicitud de datos espera respuesta:\n" : count($pendientes) . " solicitudes de datos esperan respuesta:\n";
    foreach ($pendientes as $p) {
        $horas = $p['horas'];
        printf(
            "  n.º %d  %s  persona %d  lleva %s\n",
            $p['id'],
            $p['derecho'],
            $p['persona_id'],
            $horas < 24 ? "{$horas} h" : intdiv($horas, 24) . ' d ' . ($horas % 24) . ' h'
        );
    }
    echo "\nLéelas con: php bin/solicitudes.php --ver=N (y recuerda comprobar quién pide: docs/SOLICITUDES.md).\n";
} catch (\PDOException) {
    fwrite(STDERR, "No se pudo leer las solicitudes (¿falta correr bin/migrate.php genealogy?).\n");
    exit(2);
}
