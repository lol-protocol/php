<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Una muestra de los colores de una imagen (PNG, JPG, GIF o WebP), para saber si el texto se lee sobre ella. Con la
 * extensión `gd` se reduce la imagen a una cuadrícula de `$lado` × `$lado` y se devuelve el color medio de cada celda.
 * Un SVG no se puede muestrear sin dibujarlo, así que no tiene muestra.
 */
final class MuestraDeImagen
{
    /**
     * Los colores `#rrggbb` de la cuadrícula, o null si no se puede muestrear (SVG, sin `gd` o imagen ilegible).
     * Lo transparente cuenta como blanco.
     *
     * @return list<string>|null
     */
    public static function colores(string $ruta, int $lado = 16): ?array
    {
        if (strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'svg' || !function_exists('imagecreatefromstring') || !is_file($ruta)) {
            return null;
        }
        $contenido = file_get_contents($ruta);
        $imagen = $contenido === false ? false : @imagecreatefromstring($contenido);
        if ($imagen === false) {
            return null;
        }
        $reducida = imagecreatetruecolor($lado, $lado);
        if ($reducida === false) {
            return null;
        }
        // Sobre blanco, para que lo transparente no cuente como negro.
        imagefill($reducida, 0, 0, (int) imagecolorallocate($reducida, 255, 255, 255));
        imagecopyresampled($reducida, $imagen, 0, 0, 0, 0, $lado, $lado, imagesx($imagen), imagesy($imagen));

        $colores = [];
        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                $punto = imagecolorat($reducida, $x, $y);
                $colores[] = sprintf('#%02x%02x%02x', ($punto >> 16) & 0xFF, ($punto >> 8) & 0xFF, $punto & 0xFF);
            }
        }

        return $colores;
    }
}
