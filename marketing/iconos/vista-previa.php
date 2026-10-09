#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Genera vista-previa.svg: una hoja con todos los íconos de esta carpeta.
 *
 * Los íconos usan currentColor, así que cargados con <img> (como hace el
 * README) salen siempre negros y se pierden sobre un fondo oscuro. La hoja
 * fija el color con prefers-color-scheme y deja los íconos intactos, de modo
 * que siguen heredando el color cuando se usan en línea.
 *
 * Uso: php marketing/iconos/vista-previa.php
 */

const COLUMNAS = 6;
const CELDA_ANCHO = 104;
const CELDA_ALTO = 92;
const ICONO = 48;

/** @return list<string> nombres de los íconos (sin .svg), en orden alfabético */
function nombresDeIconos(string $carpeta): array
{
    $nombres = [];
    foreach (glob($carpeta . '/*.svg') ?: [] as $ruta) {
        $nombre = basename($ruta, '.svg');
        if ($nombre !== 'vista-previa') {
            $nombres[] = $nombre;
        }
    }
    sort($nombres);
    return $nombres;
}

/** El contenido de un ícono, sin la etiqueta <svg> que lo envuelve. */
function interiorDelIcono(string $ruta): string
{
    $svg = trim((string) file_get_contents($ruta));
    if (preg_match('/^<svg\b[^>]*>(.*)<\/svg>$/s', $svg, $m) !== 1) {
        fwrite(STDERR, "Error: no se pudo leer $ruta como un <svg> simple.\n");
        exit(1);
    }
    return $m[1];
}

function hoja(string $carpeta): string
{
    $nombres = nombresDeIconos($carpeta);
    $filas = (int) ceil(count($nombres) / COLUMNAS);
    $ancho = COLUMNAS * CELDA_ANCHO;
    $alto = $filas * CELDA_ALTO;

    $celdas = '';
    foreach ($nombres as $i => $nombre) {
        $x = ($i % COLUMNAS) * CELDA_ANCHO;
        $y = intdiv($i, COLUMNAS) * CELDA_ALTO;
        $xIcono = $x + (CELDA_ANCHO - ICONO) / 2;
        $xTexto = $x + CELDA_ANCHO / 2;
        $interior = interiorDelIcono($carpeta . "/$nombre.svg");
        $celdas .= sprintf(
            "  <svg class=\"i\" x=\"%s\" y=\"%d\" width=\"%d\" height=\"%d\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.75\" stroke-linecap=\"round\" stroke-linejoin=\"round\">%s</svg>\n",
            $xIcono, $y + 8, ICONO, ICONO, $interior
        );
        $celdas .= sprintf("  <text class=\"t\" x=\"%s\" y=\"%d\" text-anchor=\"middle\">%s</text>\n", $xTexto, $y + 80, $nombre);
    }

    return <<<SVG
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$ancho} {$alto}" width="{$ancho}" height="{$alto}" role="img" aria-label="Vista previa de los íconos">
      <style>
        .i { color: #1f2937; }
        .t { fill: #4b5563; font: 12px system-ui, -apple-system, "Segoe UI", sans-serif; }
        @media (prefers-color-scheme: dark) {
          .i { color: #e5e7eb; }
          .t { fill: #9ca3af; }
        }
      </style>
    {$celdas}</svg>

    SVG;
}

file_put_contents(__DIR__ . '/vista-previa.svg', hoja(__DIR__));
echo 'Listo: ' . count(nombresDeIconos(__DIR__)) . " íconos en vista-previa.svg\n";
