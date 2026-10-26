<?php

declare(strict_types=1);

/**
 * Watches the pedidos de retiro (/r/{id}/) that wait for the owner in /0/3/. Made for cron, which mails
 * whatever a job prints to whoever MAILTO names: it prints nothing when there is nothing waiting.
 *
 *   php bin/pedidos-retiro.php                  # list what is waiting, flagging what is late
 *   php bin/pedidos-retiro.php --ocultar        # and hide the late ones while they are reviewed
 *   php bin/pedidos-retiro.php --ocultar --solo-ocultados   # same, but print only when it hid something
 *   php bin/pedidos-retiro.php --horas=48       # another deadline (default: RETIRO_PLAZO_HORAS, else 24)
 *
 * Hiding is the owner's decision, so it only happens with --ocultar: receiving a pedido hides nothing,
 * or anyone could take any page down. With the flag, a pedido that is still pending after the deadline
 * is hidden (state 'oculto', as if the owner had pressed "ocultar mientras revisa") and its record says
 * it was automatic and why. One the owner decides meanwhile is never touched.
 *
 * The listing has the number, the page and the reason of each pedido, and how long it has waited: never
 * who asked nor what they wrote, because cron mail travels in the clear.
 *
 * It lists everything that waits on every run, so a job that runs every half hour would mail every half hour
 * for as long as one pedido waits. --solo-ocultados is for that job: it prints (the same listing) only on the
 * run that hid something, so the owner learns a page went down and is not told again at the next run. The
 * daily job without it is the reminder of what still waits.
 *
 * Exit code: 0 when nothing is late, 1 when some pedido is late and still pending, 2 on a usage error
 * or when the database is not ready.
 */

use App\Repositories\Genealogy\RetiroRepository;
use App\Support\Config;
use App\Support\Database;

require __DIR__ . '/../vendor/autoload.php';

Config::load();

$uso = "Uso: php bin/pedidos-retiro.php [--horas=24] [--ocultar [--solo-ocultados]]\n";
$ocultar = false;
$soloOcultados = false;
$horas = Config::getInt('RETIRO_PLAZO_HORAS', RetiroRepository::PLAZO_HORAS);
foreach (array_slice($argv, 1) as $argumento) {
    if ($argumento === '--ocultar') {
        $ocultar = true;
    } elseif ($argumento === '--solo-ocultados') {
        $soloOcultados = true;
    } elseif (preg_match('/^--horas=(\d+)$/', $argumento, $m) === 1) {
        $horas = (int)$m[1];
    } else {
        fwrite(STDERR, $uso);
        exit(2);
    }
}
if ($horas < 1 || $horas > 8760) {
    fwrite(STDERR, $uso . "El plazo es un número de horas de 1 a 8760.\n");
    exit(2);
}
if ($soloOcultados && !$ocultar) {
    fwrite(STDERR, $uso . "--solo-ocultados va con --ocultar: sin ocultar nada no tendría nada que decir.\n");
    exit(2);
}

try {
    $repo = new RetiroRepository(Database::forSite('genealogy'));
    $pendientes = $repo->pendientes();
    $ocultados = $ocultar ? $repo->ocultarVencidos($horas) : [];
} catch (\PDOException $e) {
    fwrite(STDERR, "No se pudo leer los pedidos de retiro (¿falta correr bin/migrate.php genealogy?).\n");
    exit(2);
}

if ($pendientes === [] || ($soloOcultados && $ocultados === [])) {
    exit(0);
}

$espera = static fn(int $h): string => $h < 24 ? "{$h} h" : intdiv($h, 24) . ' d ' . ($h % 24) . ' h';
$tarde = static fn(array $p): bool => $p['horas'] >= $horas;

$vencidos = 0;
echo count($pendientes) === 1
    ? "1 pedido de retiro espera respuesta (plazo: {$horas} h):\n"
    : count($pendientes) . " pedidos de retiro esperan respuesta (plazo: {$horas} h):\n";
foreach ($pendientes as $p) {
    $queda = in_array($p['id'], $ocultados, true) ? 'VENCIDO: oculto automáticamente' : ($tarde($p) ? 'VENCIDO' : '');
    if ($tarde($p) && !in_array($p['id'], $ocultados, true)) {
        $vencidos++;
    }
    printf(
        "  n.º %d  %s %d  «%s»  espera hace %s%s\n",
        $p['id'],
        $p['tipo'],
        $p['recurso_id'],
        RetiroRepository::MOTIVOS[$p['motivo']] ?? $p['motivo'],
        $espera($p['horas']),
        $queda === '' ? '' : "  {$queda}"
    );
}
echo "\nDecide en /0/3/: ocultar mientras revisa, retirar o rechazar.\n";

exit($vencidos > 0 ? 1 : 0);
