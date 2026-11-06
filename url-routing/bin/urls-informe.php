<?php

declare(strict_types=1);

/**
 * Which URLs answered 404 or 301 lately, by shape (see Support/RegistroDeUrls.php: never the path, only what kind of
 * segments it had), to find broken inbound links and to see which old URLs still get visits.
 *
 *   php bin/urls-informe.php [--dias=7] [--maximo=20]
 *
 * It reads the day files in URL_LOG_DIR (or the folder in the system temp dir). Nothing is changed.
 */

use App\Support\Config;
use App\Support\RegistroDeUrls;

require __DIR__ . '/../vendor/autoload.php';

Config::load();

$dias = 7;
$maximo = 20;
foreach (array_slice($argv, 1) as $argumento) {
    if (preg_match('/^--dias=(\d{1,3})$/', $argumento, $m) === 1 && (int)$m[1] >= 1) {
        $dias = (int)$m[1];
    } elseif (preg_match('/^--maximo=(\d{1,4})$/', $argumento, $m) === 1 && (int)$m[1] >= 1) {
        $maximo = (int)$m[1];
    } else {
        fwrite(STDERR, "Uso: php bin/urls-informe.php [--dias=7] [--maximo=20]\n");
        exit(2);
    }
}

$filas = RegistroDeUrls::desdeConfig()->resumen($dias);
if ($filas === []) {
    echo "Sin 404 ni 301 en los últimos {$dias} días.\n";
    exit(0);
}

$secciones = [
    404 => ['404: no existe (la forma de la URL, y cuántas veces)', fn(array $f): string => sprintf('%6d  %s', $f['total'], $f['forma'])],
    301 => ['301: se redirigió (de dónde, a dónde)', fn(array $f): string => sprintf('%6d  %s  →  %s', $f['total'], $f['forma'], $f['destino'])],
];
foreach ($secciones as $estado => [$titulo, $formato]) {
    $delEstado = array_values(array_filter($filas, static fn(array $f): bool => $f['estado'] === $estado));
    $total = array_sum(array_column($delEstado, 'total'));
    echo "\n{$titulo} — últimos {$dias} días: {$total}\n";
    foreach (array_slice($delEstado, 0, $maximo) as $fila) {
        echo '  ' . $formato($fila) . "\n";
    }
    if (count($delEstado) > $maximo) {
        echo '  … y ' . (count($delEstado) - $maximo) . " formas más (--maximo=N)\n";
    }
    if ($delEstado === []) {
        echo "  ninguno\n";
    }
}
