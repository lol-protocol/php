<?php
declare(strict_types=1);

// Cargador mínimo: ChatLibros\Libro -> src/Libro.php. Sin Composer.
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'ChatLibros\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $archivo = __DIR__ . '/' . substr($clase, strlen($prefijo)) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
