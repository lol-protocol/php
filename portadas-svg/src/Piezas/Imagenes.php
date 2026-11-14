<?php

declare(strict_types=1);

namespace PortadasSvg\Piezas;

use PortadasSvg\Libro;
use PortadasSvg\Rectangulo;
use PortadasSvg\Svg;

/**
 * La imagen del libro (su ilustración o su marca de agua) y el logo: se incrustan en el SVG como `data:`.
 *
 * Es parte de `Plantilla` (se usa desde ella) y se apoya en sus ayudas de texto y de dibujo.
 */
trait Imagenes
{
    /** Marca de agua: la imagen del libro, detrás del texto de la portada, con esta opacidad. */
    protected const MARCA_DE_AGUA_OPACIDAD = 0.25;

    /**
     * Si la plantilla dibuja la `imagen` del libro. Por defecto sí: como marca de agua tenue detrás del texto (ver
     * `marcaDeAgua`) o, en `ilustrada`, como ilustración. Una plantilla solo de letra la ignora.
     */
    public function dibujaImagen(): bool
    {
        return true;
    }

    /**
     * La imagen del libro como marca de agua: cubre toda la portada, sangrado incluido, con poca opacidad para que el
     * texto se siga leyendo. Una plantilla que dibuja la imagen a su manera (`ilustrada`) devuelve vacío aquí.
     */
    protected function marcaDeAgua(Libro $libro, Rectangulo $panel): string
    {
        if ($libro->imagen === null || !$this->dibujaImagen()) {
            return '';
        }
        $sangrado = $libro->formato->sangrado;
        $zona = new Rectangulo($panel->x, $panel->y - $sangrado, $panel->ancho + $sangrado, $panel->alto + 2 * $sangrado);

        return Svg::elemento('g', ['opacity' => self::MARCA_DE_AGUA_OPACIDAD], $this->imagen($libro->imagen, $zona, 'xMidYMid slice'));
    }

    /**
     * Una imagen del libro (su ilustración o el logo) incrustada en `zona`, para que el SVG no dependa de ningún archivo.
     * `$ajuste` es el `preserveAspectRatio`: `slice` rellena la zona recortando lo que sobre, `meet` la deja entera.
     */
    protected function imagen(string $ruta, Rectangulo $zona, string $ajuste): string
    {
        $tipos = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $contenido = is_file($ruta) ? file_get_contents($ruta) : false;
        if ($contenido === false) {
            throw new \RuntimeException("No se pudo leer la imagen $ruta.");
        }

        return Svg::elemento('image', [
            'x' => $zona->x, 'y' => $zona->y, 'width' => $zona->ancho, 'height' => $zona->alto,
            'preserveAspectRatio' => $ajuste,
            'xlink:href' => 'data:' . ($tipos[strtolower(pathinfo($ruta, PATHINFO_EXTENSION))] ?? 'application/octet-stream') . ';base64,' . base64_encode($contenido),
        ]);
    }
}
