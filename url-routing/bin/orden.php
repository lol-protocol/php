<?php

declare(strict_types=1);

/**
 * Avanza el estado de una orden del sitio POS y lo anota en su historial (lo que el cliente ve en el
 * seguimiento de su orden):
 *
 *   php bin/orden.php <id> <estado> [--detalle="Texto"] [--guia=NUMERO]
 *
 * <estado> es uno de OrdenRepository::ESTADOS (pendiente, pagada, enviada, entregada, cancelada, devuelta).
 * --guia guarda el número de seguimiento del envío. Cualquier estado puede seguir a cualquier otro; no
 * devuelve el stock de una orden cancelada.
 *
 * Código de salida: 0 si la orden cambió, 1 si no existe, 2 por un error de uso o si la base no está lista.
 */

use App\Repositories\POS\OrdenRepository;
use App\Support\Config;
use App\Support\Database;

require __DIR__ . '/../vendor/autoload.php';

Config::load();

$uso = 'Uso: php bin/orden.php <id> <estado> [--detalle="Texto"] [--guia=NUMERO]' . "\n"
    . '  estados: ' . implode(', ', OrdenRepository::ESTADOS) . "\n";
$posicionales = [];
$detalle = null;
$guia = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--detalle=')) {
        $detalle = substr($arg, 10);
    } elseif (str_starts_with($arg, '--guia=')) {
        $guia = substr($arg, 7);
    } elseif (!str_starts_with($arg, '--')) {
        $posicionales[] = $arg;
    } else {
        fwrite(STDERR, $uso);
        exit(2);
    }
}

if (count($posicionales) !== 2 || !ctype_digit($posicionales[0]) || !in_array($posicionales[1], OrdenRepository::ESTADOS, true)
    || $detalle === '' || $guia === '') {
    fwrite(STDERR, $uso);
    exit(2);
}

try {
    $cambio = (new OrdenRepository(Database::forSite('pos')))->cambiarEstado((int)$posicionales[0], $posicionales[1], $detalle, $guia);
} catch (\PDOException $e) {
    fwrite(STDERR, 'La base de datos no está lista: ' . $e->getMessage() . "\n");
    exit(2);
}

if (!$cambio) {
    fwrite(STDERR, "No hay una orden con id {$posicionales[0]}.\n");
    exit(1);
}
echo "Orden {$posicionales[0]}: {$posicionales[1]}\n";
