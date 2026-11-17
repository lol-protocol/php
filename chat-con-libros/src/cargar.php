<?php
declare(strict_types=1);

// Cargador mínimo: ChatLibros\Libro -> src/Libro.php. El núcleo no necesita Composer.
// Solo si existe vendor/ (el modo con IA instala el SDK de Anthropic con Composer), se carga también.
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

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
