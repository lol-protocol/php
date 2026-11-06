<?php

declare(strict_types=1);

/**
 * Pruebas de portadas-svg, sin dependencias. Uso: php pruebas/ejecutar.php
 *
 * Las pruebas están en pruebas/temas/*.php (cada archivo devuelve un arreglo nombre => función) y las funciones que
 * comparten, en pruebas/ayudas.php. Con --actualizar-dibujos se regenera pruebas/dibujos.json.
 */

require __DIR__ . '/../src/autocarga.php';
require __DIR__ . '/ayudas.php';

/** @var array<string, callable(): void> $pruebas */
$pruebas = [];
foreach (glob(__DIR__ . '/temas/*.php') ?: [] as $tema) {
    $deTema = require $tema;
    if (!is_array($deTema)) {
        throw new RuntimeException(basename($tema) . ' tiene que devolver un arreglo nombre => función.');
    }
    foreach ($deTema as $nombre => $cuerpo) {
        if (!is_string($nombre) || !is_callable($cuerpo)) {
            throw new RuntimeException(basename($tema) . ': cada prueba es un nombre => función.');
        }
        if (isset($pruebas[$nombre])) {
            throw new RuntimeException("La prueba «{$nombre}» está repetida (en " . basename($tema) . ').');
        }
        $pruebas[$nombre] = $cuerpo;
    }
}

if (in_array('--actualizar-dibujos', $argv, true)) {
    $huellas = huellasDeDibujos();
    ksort($huellas);
    file_put_contents(__DIR__ . '/dibujos.json', json_encode($huellas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    echo count($huellas) . " huellas escritas en pruebas/dibujos.json.\n";
    exit(0);
}

$fallidas = 0;
foreach ($pruebas as $nombre => $cuerpo) {
    try {
        $cuerpo();
        echo "ok     $nombre\n";
    } catch (Throwable $error) {
        $fallidas++;
        echo "FALLO  $nombre\n       " . $error->getMessage() . "\n";
    }
}

echo count($pruebas) - $fallidas . ' de ' . count($pruebas) . " pruebas pasan.\n";
exit($fallidas === 0 ? 0 : 1);
