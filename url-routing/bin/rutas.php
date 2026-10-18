<?php

declare(strict_types=1);

/**
 * Prints every URL both sites answer, from config/routes/*.php, as Markdown:
 *
 *   php bin/rutas.php > docs/RUTAS.md
 *
 * A test fails when docs/RUTAS.md is not what this prints.
 */

use App\Support\TablaDeRutas;

require __DIR__ . '/../vendor/autoload.php';

echo TablaDeRutas::markdown(__DIR__ . '/../config/routes');
