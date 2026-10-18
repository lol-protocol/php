<?php

declare(strict_types=1);

namespace PortadasSvg\Plantillas;

use PortadasSvg\Libro;
use PortadasSvg\Plantilla;
use PortadasSvg\Rectangulo;
use PortadasSvg\Texto;

/** Libro sobrio: fondo oscuro, sans, título a la izquierda y lomo en el color de acento. */
final class Sobria extends Plantilla
{
    public function coloresPorDefecto(): array
    {
        return ['fondo' => '#1c1f24', 'texto' => '#f2efe6', 'acento' => '#c8a24a'];
    }

    protected function fuente(): string
    {
        return "'Helvetica Neue', Helvetica, Arial, sans-serif";
    }

    protected function factorAncho(): float
    {
        return 0.55;
    }

    protected function coloresLomo(Libro $libro): array
    {
        return ['fondo' => $libro->colores['acento'], 'texto' => $libro->colores['fondo']];
    }

    protected function portada(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $x = $panel->x + self::MARGEN;
        $ancho = $panel->ancho - 2 * self::MARGEN;

        $svg = $this->rectangulo(new Rectangulo($x, $panel->y + 14, 18, 2.4), ['fill' => $colores['acento']]);

        $titulo = Texto::ajustar($libro->titulo, $ancho, $panel->alto * 0.42, 16.0, 8.0, $this->factorAncho(), self::INTERLINEADO);
        $y = $panel->y + 30 + $titulo['tamano'] * 0.8;
        $svg .= $this->parrafo($titulo['lineas'], $x, $y, $titulo['tamano'], 'start', $colores['texto'], ['font-weight' => 'bold']);
        $fin = $y + (count($titulo['lineas']) - 1) * $titulo['tamano'] * self::INTERLINEADO;

        if ($libro->subtitulo !== '') {
            $subtitulo = Texto::ajustar($libro->subtitulo, $ancho, 20.0, 5.0, 3.2, $this->factorAncho(), self::INTERLINEADO);
            $svg .= $this->parrafo($subtitulo['lineas'], $x, $fin + 9, $subtitulo['tamano'], 'start', $colores['acento']);
        }

        // Abajo: una regla de acento, el autor en mayúsculas espaciadas, el traductor y la editorial.
        $autorY = $panel->y + $panel->alto - self::MARGEN - 12;
        $svg .= $this->rectangulo(new Rectangulo($x, $autorY - 9, 12, 0.8), ['fill' => $colores['acento']]);
        $svg .= $this->parrafo([mb_strtoupper($libro->autor)], $x, $autorY, 5.2, 'start', $colores['texto'], ['letter-spacing' => 1.2]);
        if ($libro->traductor !== '') {
            $svg .= $this->parrafo(['Traducción de ' . $libro->traductor], $x, $autorY + 7, 3.4, 'start', $colores['texto']);
        }
        $svg .= $this->editorial($libro, $x, $panel->y + $panel->alto - self::MARGEN + 4, 'start');

        return $svg;
    }
}
