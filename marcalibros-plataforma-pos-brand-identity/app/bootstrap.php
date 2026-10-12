<?php

/**
 * Carga las clases de la aplicación y del módulo de marcalibros.
 *
 * Con Composer (`composer install`) se usa su cargador. Sin Composer funciona
 * igual: la aplicación no depende de ningún paquete externo.
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'MarcalibrosApp\\' => __DIR__ . '/src/',
        'Marcalibros\\' => dirname(__DIR__) . '/php/src/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
