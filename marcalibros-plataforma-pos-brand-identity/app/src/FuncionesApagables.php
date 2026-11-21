<?php

namespace MarcalibrosApp;

use LolProtocol\Interruptores\Interruptores;

/**
 * Lo que el equipo puede apagar de la aplicación con los interruptores del repositorio (`../../interruptores/`).
 *
 * Es opcional: sin la variable de entorno MARCALIBROS_INTERRUPTORES (la ruta de esa carpeta) no se lee nada y la
 * aplicación es la de siempre. Con ella:
 *  - `pos.mb-h-app` apagada corta toda la aplicación (404 «ocultar» o 503 «no disponible», según su política);
 *  - cada categoría del catálogo se muestra solo si su opción está encendida (`pos.mb-h-movimientos:<slug>` o
 *    `pos.mb-h-tipos-texto:<slug>`; el id de la opción es el slug de la categoría en el manifiesto).
 * Una categoría sin opción en los interruptores se muestra: lo vigila una prueba, para que no se quede así sin querer.
 *
 * El estado se lee una sola vez por petición.
 */
final class FuncionesApagables
{
    private const APP = 'pos.mb-h-app';
    private const FUNCIONES_DE_CATEGORIAS = ['pos.mb-h-movimientos', 'pos.mb-h-tipos-texto'];

    /** @var array<string, string> slug de la categoría => referencia de su opción */
    private array $refPorCategoria = [];

    private function __construct(private readonly Interruptores $interruptores)
    {
        $this->interruptores->congelar();
        $catalogo = $this->interruptores->catalogo();
        foreach (self::FUNCIONES_DE_CATEGORIAS as $funcion) {
            foreach ($catalogo[$funcion]['opciones'] ?? [] as $opcion) {
                if (is_array($opcion) && is_string($opcion['id'] ?? null)) {
                    $this->refPorCategoria[$opcion['id']] = $funcion . ':' . $opcion['id'];
                }
            }
        }
    }

    /**
     * @param array<array-key, mixed> $env
     * @throws \Throwable si está configurado y no se puede leer (falta el código o el catálogo, o el estado está dañado)
     */
    public static function fromEnvironment(array $env): ?self
    {
        $directory = $env['MARCALIBROS_INTERRUPTORES'] ?? '';
        return is_string($directory) && $directory !== '' ? self::fromDirectory($directory) : null;
    }

    /** @throws \Throwable */
    public static function fromDirectory(string $directory): self
    {
        $directory = rtrim($directory, '/');
        if (!class_exists(Interruptores::class, false)) {
            $source = $directory . '/src/Interruptores.php';
            if (!is_file($source)) {
                throw new \RuntimeException("No hay código de interruptores en $directory (MARCALIBROS_INTERRUPTORES).");
            }
            require_once $source;
        }
        return new self(new Interruptores($directory . '/catalogo.json', $directory . '/var/estado.json'));
    }

    public function appEnabled(): bool
    {
        return $this->interruptores->activo(self::APP);
    }

    /** Qué hacer con la aplicación apagada: 'ocultar' o 'no_disponible'. */
    public function whenOff(): string
    {
        return $this->interruptores->alApagar(self::APP);
    }

    public function categoryAllowed(string $slug): bool
    {
        return !isset($this->refPorCategoria[$slug]) || $this->interruptores->activo($this->refPorCategoria[$slug]);
    }
}
