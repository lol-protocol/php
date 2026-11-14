<?php

declare(strict_types=1);

namespace PortadasSvg\Piezas;

use PortadasSvg\Adornos\Motivo;
use PortadasSvg\Libro;
use PortadasSvg\Rectangulo;
use PortadasSvg\Svg;
use PortadasSvg\Texto;

/**
 * La contratapa de una plantilla: friso o raya, cita, texto, emblema de marca de agua, motivo, logo, pie y hueco del código de barras.
 *
 * Es parte de `Plantilla` (se usa desde ella) y se apoya en sus ayudas de texto y de dibujo.
 */
trait Contratapa
{
    /** Contratapa: raya de acento (distancia al borde superior del corte, largo y grosor). */
    protected const CONTRATAPA_RAYA_Y = 22.0;
    protected const CONTRATAPA_RAYA_LARGO = 12.0;
    protected const CONTRATAPA_RAYA_GROSOR = 0.8;
    /** Contratapa: friso del adorno de la tradición, que sustituye a la raya (distancia al borde superior, ancho y alto). */
    protected const CONTRATAPA_FRISO_Y = 17.0;
    protected const CONTRATAPA_FRISO_ANCHO = 36.0;
    protected const CONTRATAPA_FRISO_ALTO = 5.0;
    /** Contratapa corta: marca de agua con el emblema de la tradición (radio, altura de su centro como fracción del alto, opacidad y grosor de línea). */
    protected const CONTRATAPA_TEXTO_SEPARACION = 8.0;
    protected const CONTRATAPA_MARCA_RADIO = 24.0;
    protected const CONTRATAPA_MARCA_Y = 0.64;
    protected const CONTRATAPA_MARCA_OPACIDAD = 0.3;
    protected const CONTRATAPA_MARCA_TRAZO = 0.25;
    /** Contratapa: motivo de la categoría, abajo a la izquierda sobre el pie (radio y distancia de su centro al borde inferior). */
    protected const CONTRATAPA_MOTIVO_RADIO = 6.5;
    protected const CONTRATAPA_MOTIVO_Y = 30.0;
    /** Contratapa: dónde empieza el texto desde el borde superior, qué fracción del alto puede ocupar y su tamaño. */
    protected const CONTRATAPA_TEXTO_Y = 34.0;
    protected const CONTRATAPA_TEXTO_ALTO = 0.5;
    protected const CONTRATAPA_TAMANO_MAX = 4.2;
    protected const CONTRATAPA_TAMANO_MIN = 2.8;
    /** Contratapa: la cita que la abre (alto máximo, tamaño, espacio libre bajo ella) y la línea de su autor (tamaño y separación). */
    protected const CITA_ALTO = 34.0;
    protected const CITA_TAMANO_MAX = 5.6;
    protected const CITA_TAMANO_MIN = 3.4;
    protected const CITA_SEPARACION = 8.0;
    protected const CITA_AUTOR_TAMANO = 3.2;
    protected const CITA_AUTOR_SEPARACION = 3.0;
    /** Pie con editorial y colección: distancia de su línea base al borde inferior del corte, y tamaño. */
    protected const PIE_Y = 12.0;
    protected const PIE_TAMANO_MAX = 3.2;
    protected const PIE_TAMANO_MIN = 2.4;
    /** Logo de la editorial en la contratapa: ancho y alto máximos, y distancia de su borde superior al del corte. */
    protected const LOGO_ANCHO = 26.0;
    protected const LOGO_ALTO = 12.0;
    protected const LOGO_Y = 10.0;
    /** Hueco del código de barras: ancho, alto, distancia de su borde superior al borde inferior del corte, y espacio libre hasta el pie. */
    protected const CODIGO_ANCHO = 42.0;
    protected const CODIGO_ALTO = 26.0;
    protected const CODIGO_Y = 38.0;
    protected const CODIGO_SEPARACION = 4.0;
    /** ISBN: separación sobre el hueco del código y tamaño. */
    protected const ISBN_SEPARACION = 3.0;
    protected const ISBN_TAMANO_MAX = 2.8;
    protected const ISBN_TAMANO_MIN = 2.0;

    protected function contratapa(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $x = $panel->x + self::MARGEN;
        $ancho = $panel->ancho - 2 * self::MARGEN;

        // Arriba, el friso de la tradición; si no tiene, una raya de acento.
        $svg = $this->adorno($libro)->friso(new Rectangulo($x, $panel->y + self::CONTRATAPA_FRISO_Y, self::CONTRATAPA_FRISO_ANCHO, self::CONTRATAPA_FRISO_ALTO), $colores['acento']);
        if ($svg === '') {
            $svg = $this->linea($x, $panel->y + self::CONTRATAPA_RAYA_Y, $x + self::CONTRATAPA_RAYA_LARGO, $panel->y + self::CONTRATAPA_RAYA_Y, $colores['acento'], self::CONTRATAPA_RAYA_GROSOR);
        }
        $y = $panel->y + self::CONTRATAPA_TEXTO_Y;
        $alto = $panel->alto * self::CONTRATAPA_TEXTO_ALTO;
        if ($libro->textos->cita !== '') {
            // La cita abre la contratapa, más grande y con la letra propia de la plantilla, y firmada por el autor.
            $estilo = $this->estiloDeCita();
            $cita = Texto::ajustar('«' . $libro->textos->cita . '»', $ancho, self::CITA_ALTO, self::CITA_TAMANO_MAX, self::CITA_TAMANO_MIN, $this->medidaDe($estilo), self::INTERLINEADO, true);
            $svg .= $this->parrafo($cita['lineas'], $x, $y + $cita['tamano'] * self::ASCENDENTE, $cita['tamano'], 'start', $colores['texto'], $estilo);
            $firma = $y + $cita['tamano'] * self::ASCENDENTE + (count($cita['lineas']) - 1) * $cita['tamano'] * self::INTERLINEADO + $cita['tamano'] * self::DESCENDENTE + self::CITA_AUTOR_SEPARACION + self::CITA_AUTOR_TAMANO * self::ASCENDENTE;
            $svg .= $this->rotulo('— ' . $libro->textos->autor, $x, $firma, $ancho, self::CITA_AUTOR_TAMANO, self::CONTRATAPA_TAMANO_MIN, 'start', $colores['acento']);
            $gastado = $firma + self::CITA_AUTOR_TAMANO * self::DESCENDENTE + self::CITA_SEPARACION - $y;
            $y += $gastado;
            $alto -= $gastado;
        }
        $fin = $y;
        if ($libro->textos->contratapa !== '') {
            $texto = Texto::ajustar($libro->textos->contratapa, $ancho, $alto, self::CONTRATAPA_TAMANO_MAX, self::CONTRATAPA_TAMANO_MIN, $this->medidaDe(), self::INTERLINEADO);
            $svg .= $this->parrafo($texto['lineas'], $x, $y + $texto['tamano'] * self::ASCENDENTE, $texto['tamano'], 'start', $colores['texto'], [], $ancho);
            $fin = $y + count($texto['lineas']) * $texto['tamano'] * self::INTERLINEADO;
        }

        // Una contratapa con poco texto queda vacía: el emblema de la tradición, muy tenue, llena el hueco. Solo
        // se pone si cabe sin tocar el texto.
        $centro = $panel->y + $panel->alto * self::CONTRATAPA_MARCA_Y;
        if ($libro->tradicion !== null && $centro - self::CONTRATAPA_MARCA_RADIO > $fin + self::CONTRATAPA_TEXTO_SEPARACION) {
            $svg .= Svg::elemento('g', ['opacity' => self::CONTRATAPA_MARCA_OPACIDAD], $this->adorno($libro)->emblema($panel->x + $panel->ancho / 2, $centro, self::CONTRATAPA_MARCA_RADIO, $colores['acento'], self::CONTRATAPA_MARCA_TRAZO));
        }

        if ($libro->categoria !== null) {
            $svg .= Motivo::dibujar($libro->categoria, $x + self::CONTRATAPA_MOTIVO_RADIO, $panel->y + $panel->alto - self::CONTRATAPA_MOTIVO_Y, self::CONTRATAPA_MOTIVO_RADIO, $colores['acento']);
        }

        if ($libro->logo !== null) {
            $svg .= $this->imagen($libro->logo, new Rectangulo($panel->x + $panel->ancho - self::MARGEN - self::LOGO_ANCHO, $panel->y + self::LOGO_Y, self::LOGO_ANCHO, self::LOGO_ALTO), 'xMaxYMid meet');
        }

        // Hueco en blanco para el código de barras del ISBN: el código lo pone quien imprime. El pie
        // llega hasta el hueco y deja un espacio libre antes de él.
        $caja = new Rectangulo($panel->x + $panel->ancho - self::MARGEN - self::CODIGO_ANCHO, $panel->y + $panel->alto - self::CODIGO_Y, self::CODIGO_ANCHO, self::CODIGO_ALTO);
        $svg .= $this->editorial($libro, $x, $panel->y + $panel->alto - self::PIE_Y, 'start', $ancho - self::CODIGO_ANCHO - self::CODIGO_SEPARACION);
        $svg .= $this->rectangulo($caja, ['fill' => '#ffffff']);
        if ($libro->textos->isbn !== '') {
            $svg .= $this->rotulo('ISBN ' . $libro->textos->isbn, $caja->x + self::CODIGO_ANCHO, $caja->y - self::ISBN_SEPARACION, self::CODIGO_ANCHO, self::ISBN_TAMANO_MAX, self::ISBN_TAMANO_MIN, 'end', $colores['texto']);
        }

        return $svg;
    }

    /** Pie con editorial y colección, si hay alguna, en una sola línea que cabe en `ancho`. */
    protected function editorial(Libro $libro, float $x, float $y, string $anclaje, float $ancho): string
    {
        $pie = implode(' · ', array_filter([$libro->textos->editorial, $libro->textos->coleccion, $this->rotuloDeVolumen($libro)], static fn (string $dato): bool => $dato !== ''));

        return $pie === '' ? '' : $this->rotulo($pie, $x, $y, $ancho, self::PIE_TAMANO_MAX, self::PIE_TAMANO_MIN, $anclaje, $libro->colores['texto']);
    }
}
