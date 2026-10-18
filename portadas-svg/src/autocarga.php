<?php

declare(strict_types=1);

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'PortadasSvg\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $archivo = __DIR__ . '/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
