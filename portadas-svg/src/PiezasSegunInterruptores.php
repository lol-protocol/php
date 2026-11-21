<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Qué piezas ocultar según los interruptores del repositorio (`interruptores/`, función `pos.portadas`).
 *
 * Solo se usa con `--interruptores=<carpeta>`: sin esa opción, portadas-svg no lee nada de fuera.
 * Una pieza se oculta si la función `pos.portadas` está apagada o si su opción está apagada.
 * La cubierta completa y el sobrelomo son la misma opción (`cubierta-completa`).
 */
final class PiezasSegunInterruptores
{
    /** Archivo de pieza => opción de pos.portadas. */
    public const OPCION = [
        'portada.svg' => 'portada',
        'contratapa.svg' => 'contratapa',
        'lomo.svg' => 'lomo',
        'cubierta-completa.svg' => 'cubierta-completa',
        'sobrelomo.svg' => 'cubierta-completa',
    ];

    /**
     * @return list<string> archivos de pieza que no se deben escribir
     * @throws \RuntimeException si la carpeta no tiene el código de interruptores
     * @throws \Throwable si el catálogo o el estado no se pueden leer (un error, no un «no oculto»)
     */
    public static function ocultas(string $carpeta): array
    {
        $carpeta = rtrim($carpeta, '/');
        $fuente = "$carpeta/src/Interruptores.php";
        if (!is_file($fuente)) {
            throw new \RuntimeException("no hay código de interruptores en «{$carpeta}» (falta src/Interruptores.php)");
        }
        // El código se carga una sola vez: la clase no depende de su carpeta, solo recibe las rutas del catálogo y del estado.
        if (!class_exists(\LolProtocol\Interruptores\Interruptores::class, false)) {
            require_once $fuente;
        }
        $cat = new \LolProtocol\Interruptores\Interruptores("$carpeta/catalogo.json", "$carpeta/var/estado.json");

        $ocultas = [];
        foreach (self::OPCION as $archivo => $opcion) {
            if (!$cat->activo('pos.portadas') || !$cat->activo("pos.portadas:{$opcion}")) {
                $ocultas[] = $archivo;
            }
        }
        return $ocultas;
    }
}
