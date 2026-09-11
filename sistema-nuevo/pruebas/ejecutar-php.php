<?php

declare(strict_types=1);

/**
 * Corre todas las pruebas PHP (pruebas/php/*-test.php) contra el saneador.
 * Uso: php pruebas/ejecutar-php.php
 */

require __DIR__ . '/marco-pruebas.php';
require __DIR__ . '/../servidor-php/codigo/saneador.php';

foreach (glob(__DIR__ . '/php/*-test.php') as $archivo) {
    require $archivo;
}

exit(pruebas_resumen());
