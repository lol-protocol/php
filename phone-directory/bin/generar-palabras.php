#!/usr/bin/env php
<?php

/**
 * Writes the word lists of the PHP, Python and Java parsers from data/palabras.json (see WordListSync).
 *
 *   php bin/generar-palabras.php              escribe las regiones generadas que estén desactualizadas
 *   php bin/generar-palabras.php --comprobar  no escribe: sale con 1 si alguna lo está
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhoneDirectory\Parser\WordListSync;

$check = in_array('--comprobar', $argv, true);
$sync = new WordListSync(dirname(__DIR__));

$changed = $sync->sync(write: !$check);

if ($changed === []) {
    echo "Las listas de palabras están al día.\n";
    exit(0);
}

foreach ($changed as $file) {
    echo ($check ? 'desactualizado: ' : 'actualizado: ') . $file . "\n";
}

if ($check) {
    fwrite(STDERR, "Corre `php bin/generar-palabras.php` y commitea lo que cambie.\n");
    exit(1);
}

exit(0);
