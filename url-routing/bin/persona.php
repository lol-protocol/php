<?php

declare(strict_types=1);

/**
 * What the site holds about a persona, and erasing it (see SupresionRepository). For the owner, from the command
 * line; the web app has no page for this.
 *
 *   php bin/persona.php acceso <id>                              # everything held about her, as JSON
 *   php bin/persona.php resumen <id>                             # a draft of what to tell her: what a visitor sees and counts of the rest
 *   php bin/persona.php suprimir <id> [--pedido=N]               # shows what would be removed
 *   php bin/persona.php suprimir <id> [--pedido=N] --confirmar   # does it (it cannot be undone without a backup)
 *   php bin/persona.php rectificar <id> <tabla.columna>=<valor> [--suceso=N] [--pedido=N]   # shows what a correction would do
 *   php bin/persona.php rectificar <id> <tabla.columna>=<valor> [--suceso=N] [--pedido=N] --confirmar   # does it
 *   php bin/persona.php rectificacion <id> <tabla.columna> [--pedido=N]  # records a correction made by hand
 *   php bin/persona.php entrega <id> [--dias=7]                  # a link and a key to read what is held about her, hidden part included
 *   php bin/persona.php entrega <id> --revocar                   # ends every delivery of hers that still works
 *   php bin/persona.php reaplicar                                # lists who came back after restoring a backup
 *   php bin/persona.php reaplicar --confirmar                    # and removes them again
 *
 * --pedido=N is the pedido de retiro that asked for it: for suprimir it is recorded and left as 'retirado' with a note;
 * for rectificar and rectificacion it is only recorded (the field, never the values). rectificar changes one field of
 * the ficha (see RectificacionRepository::CAMPOS), says whether it changes what a visitor sees and, without
 * --confirmar, changes nothing; --suceso=N is the event, for the fields of sucesos.
 * entrega is for AFTER checking who asks (docs/SOLICITUDES.md): the page it makes shows what the site hides from a visitor.
 * It prints the link and the key once, and they must go by two different means; only their hashes are stored.
 * Exit code: 0 done, 1 there is no such persona/pedido, 2 usage error or a database that is not ready.
 * The database comes from DB_DSN_GENEALOGY (or .env), as for the web app.
 */

use App\Repositories\Genealogy\EntregaRepository;
use App\Repositories\Genealogy\RectificacionRepository;
use App\Repositories\Genealogy\RetiroRepository;
use App\Repositories\Genealogy\SupresionRepository;
use App\Support\Config;
use App\Support\Database;

require __DIR__ . '/../vendor/autoload.php';

Config::load();

$uso = "Uso: php bin/persona.php acceso <id>\n"
    . "     php bin/persona.php resumen <id>\n"
    . "     php bin/persona.php suprimir <id> [--pedido=N] [--confirmar]\n"
    . "     php bin/persona.php rectificar <id> <tabla.columna>=<valor> [--suceso=N] [--pedido=N] [--confirmar]\n"
    . "     php bin/persona.php rectificacion <id> <tabla.columna> [--pedido=N]\n"
    . "     php bin/persona.php entrega <id> [--dias=7] | entrega <id> --revocar\n"
    . "     php bin/persona.php reaplicar [--confirmar]\n";

$orden = $argv[1] ?? '';
$confirmar = false;
$pedido = null;
$id = null;
$que = null;
$dias = null;
$revocar = false;
$suceso = null;
$conId = ['acceso', 'resumen', 'suprimir', 'rectificar', 'rectificacion', 'entrega'];
foreach (array_slice($argv, 2) as $argumento) {
    if ($argumento === '--confirmar') {
        $confirmar = true;
    } elseif (preg_match('/^--pedido=(\d+)$/', $argumento, $m) === 1 && in_array($orden, ['suprimir', 'rectificar', 'rectificacion'], true)) {
        $pedido = (int)$m[1];
    } elseif (preg_match('/^--dias=(\d+)$/', $argumento, $m) === 1 && $orden === 'entrega') {
        $dias = (int)$m[1];
    } elseif ($argumento === '--revocar' && $orden === 'entrega') {
        $revocar = true;
    } elseif (preg_match('/^--suceso=(\d+)$/', $argumento, $m) === 1 && $orden === 'rectificar') {
        $suceso = (int)$m[1];
    } elseif ($id === null && ctype_digit($argumento) && in_array($orden, $conId, true)) {
        $id = (int)$argumento;
    } elseif (in_array($orden, ['rectificar', 'rectificacion'], true) && $id !== null && $que === null) {
        $que = $argumento;
    } else {
        fwrite(STDERR, $uso);
        exit(2);
    }
}
$necesitaId = in_array($orden, $conId, true);
if (
    !in_array($orden, [...$conId, 'reaplicar'], true) || $necesitaId !== ($id !== null)
    || (in_array($orden, ['acceso', 'resumen', 'rectificacion', 'entrega'], true) && $confirmar)
    || in_array($orden, ['rectificar', 'rectificacion'], true) !== ($que !== null)
    || ($orden === 'rectificar' && !str_contains((string)$que, '='))
    || ($revocar && $dias !== null)
) {
    fwrite(STDERR, $uso);
    exit(2);
}

/** @param array<string, array{total: int, ids: list<int>}> $menciones */
$imprimirMenciones = static function (array $menciones): void {
    if ($menciones === []) {
        echo "Ningún texto libre (descripciones, títulos, notas) nombra a esa persona.\n";

        return;
    }
    echo "Textos libres que nombran a esa persona: se leen y se corrigen a mano, esto no los toca.\n";
    foreach ($menciones as $donde => $hallazgo) {
        $mas = $hallazgo['total'] > count($hallazgo['ids']) ? ' …' : '';
        echo sprintf("  %s: %d (ids %s%s)\n", $donde, $hallazgo['total'], implode(', ', $hallazgo['ids']), $mas);
    }
};

try {
    $db = Database::forSite('genealogy');
    $repo = new SupresionRepository($db);

    if ($orden === 'reaplicar') {
        $volvieron = $repo->volvieron();
        if ($volvieron === []) {
            echo "Ninguna persona suprimida está en la base: no hay nada que volver a quitar.\n";
            exit(0);
        }
        echo count($volvieron) . ' ' . (count($volvieron) === 1 ? 'persona suprimida volvió' : 'personas suprimidas volvieron')
            . ' con la copia de seguridad: ' . implode(', ', $volvieron) . ".\n";
        if (!$confirmar) {
            echo "Nada cambió. Para quitarlas otra vez, repetir con --confirmar.\n";
            exit(0);
        }
        $repo->reaplicar();
        echo "Quitadas otra vez.\n";
        exit(0);
    }

    if ($orden === 'rectificar') {
        [$campo, $valor] = explode('=', (string)$que, 2);
        $r = (new RectificacionRepository($db))->rectificar((int)$id, $campo, $valor, $suceso, $pedido, $confirmar);
        echo $confirmar
            ? "Corregido {$campo} de la persona {$id}. Anotada la corrección {$r['correccion']} (sin el valor).\n"
            : "Se corregiría {$campo} de la persona {$id}.\n";
        echo RectificacionRepository::loQueVeUnVisitante($r) . "\n";
        if ($r['aviso_grupo'] !== null) {
            echo $r['aviso_grupo'] . "\n";
        }
        echo $confirmar ? "Mira la página como visitante y como propietario.\n" : "Nada cambió. Para hacerlo, repetir con --confirmar.\n";
        exit(0);
    }

    if ($orden === 'resumen') {
        $texto = $repo->resumenLegible((int)$id);
        if ($texto === null) {
            fwrite(STDERR, "No existe la persona {$id}.\n");
            exit(1);
        }
        // The draft goes alone to stdout (to paste or pipe); what to do with it, to stderr.
        fwrite(STDERR, "Borrador para revisar antes de mandar: solo dice lo que un visitante ya ve y cuántas cosas hay, nunca fechas, lugares ni nombres de parientes.\n"
            . "Añade el saludo, y comprueba antes quién pide (docs/SOLICITUDES.md).\n\n");
        echo $texto;
        exit(0);
    }

    if ($orden === 'entrega') {
        $entregas = new EntregaRepository($db);
        if ($revocar) {
            $n = $entregas->revocar((int)$id);
            echo $n === 0 ? "La persona {$id} no tenía entregas vigentes.\n" : "Se terminaron {$n} entrega" . ($n === 1 ? '' : 's') . " vigente" . ($n === 1 ? '' : 's') . " de la persona {$id}.\n";
            exit(0);
        }
        $e = $entregas->crear((int)$id, $dias ?? EntregaRepository::VIGENCIA_DIAS);
        $base = rtrim((string)Config::get('SITE_URL_GENEALOGY', ''), '/') ?: 'https://TU-DOMINIO';
        echo "Entrega {$e['id']} para la persona {$id}: vigente hasta el {$e['vence_en']}, se abre hasta " . EntregaRepository::MAX_VISTAS
            . ' veces y se bloquea con ' . EntregaRepository::MAX_INTENTOS . " claves malas.\n\n"
            . "Enlace (mándalo por un medio):\n  {$base}/e/{$e['token']}/\n\n"
            . "Clave (mándala por OTRO medio; no se vuelve a mostrar):\n  {$e['clave']}\n\n"
            . "La página muestra lo que el sitio oculta a un visitante. Mándalos solo después de comprobar quién pide (docs/SOLICITUDES.md).\n"
            . "Si se mandan al medio equivocado: php bin/persona.php entrega {$id} --revocar\n";
        exit(0);
    }

    if ($orden === 'rectificacion') {
        $numero = $repo->rectificar((int)$id, (string)$que, $pedido);
        echo "Anotada la corrección {$numero}: {$que} de la persona {$id}. No se guardó ningún valor.\n";
        exit(0);
    }

    $acceso = $repo->acceso((int)$id);
    if ($acceso === null) {
        fwrite(STDERR, "No existe la persona {$id}.\n");
        exit(1);
    }

    if ($orden === 'acceso') {
        $acceso['generado_en'] = date('Y-m-d H:i:s');
        echo json_encode($acceso, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    }

    $nombres = (string)$acceso['persona']['nombres'];
    $apellidos = (string)$acceso['persona']['apellidos'];
    echo "Persona {$id}: {$nombres} {$apellidos}\n";
    if (!$confirmar) {
        echo "Se quitaría:\n";
        foreach ($repo->resumen((int)$id) as $que => $cuantas) {
            echo "  {$que}: {$cuantas}\n";
        }
        $imprimirMenciones($repo->menciones($nombres, $apellidos));
        echo "Nada cambió. Para suprimirla, repetir con --confirmar. Sin una copia de seguridad no se puede deshacer.\n";
        exit(0);
    }

    $quitado = $repo->suprimir((int)$id, $pedido);
    echo "Suprimida. Se quitó:\n";
    foreach ($quitado as $que => $cuantas) {
        echo "  {$que}: {$cuantas}\n";
    }
    if ($pedido !== null) {
        $nota = 'Datos de la persona suprimidos el ' . date('Y-m-d') . '.';
        echo (new RetiroRepository($db))->decidir($pedido, 'retirar', $nota)
            ? "El pedido {$pedido} quedó como «retirado» con esa nota.\n"
            : "No se pudo anotar el pedido {$pedido}: anótalo en /0/3/.\n";
    }
    $imprimirMenciones($repo->menciones($nombres, $apellidos));
    echo "Si se restaura una copia de seguridad vieja, corre: php bin/persona.php reaplicar --confirmar\n";
} catch (\DomainException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (\PDOException) {
    fwrite(STDERR, "No se pudo leer la base (¿falta correr bin/migrate.php genealogy?).\n");
    exit(2);
}
