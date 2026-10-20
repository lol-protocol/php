<?php

declare(strict_types=1);

/**
 * Genera las piezas SVG de cada libro (un JSON, ver README.md). La lógica está en PortadasSvg\Cli;
 * `php bin/generar.php --help` explica el uso.
 */

require __DIR__ . '/../src/autocarga.php';

exit(PortadasSvg\Cli::ejecutar(
    array_slice($argv, 1),
    dirname(__DIR__),
    static function (string $texto): void {
        fwrite(STDOUT, $texto);
    },
    static function (string $texto): void {
        fwrite(STDERR, $texto);
    },
));
