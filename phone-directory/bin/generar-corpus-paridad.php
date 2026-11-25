#!/usr/bin/env php
<?php

/**
 * Writes tests/fixtures/parser-paridad.json: the inputs of ParityCorpus with what the PHP parser gives, which the
 * Python and Java ports must reproduce.
 *
 *   php bin/generar-corpus-paridad.php              lo escribe
 *   php bin/generar-corpus-paridad.php --comprobar  no escribe: sale con 1 si está desactualizado
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhoneDirectory\Parser\ParityCorpus;

$path = __DIR__ . '/../tests/fixtures/parser-paridad.json';
$text = ParityCorpus::encode(ParityCorpus::build());

if (in_array('--comprobar', $argv, true)) {
    if (@file_get_contents($path) === $text) {
        echo "El corpus de paridad está al día.\n";
        exit(0);
    }
    fwrite(STDERR, "El corpus de paridad está desactualizado: corre `php bin/generar-corpus-paridad.php`, mira que los puertos de Python y Java sigan dando lo mismo y commitea lo que cambie.\n");
    exit(1);
}

file_put_contents($path, $text);
echo 'Escrito ' . realpath($path) . ' (' . substr_count($text, '{"id":') . " casos).\n";
