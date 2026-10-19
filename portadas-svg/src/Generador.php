<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Escribe las piezas de un libro en una carpeta: un SVG por pieza y un resumen de medidas para la imprenta. */
final class Generador
{
    /**
     * Archivo => [vista, nombre legible]. Cada libro lleva cuatro SVG: portada, contratapa, lomo y la
     * cubierta, que es el sobrelomo si tiene solapas y la cubierta completa si no.
     */
    private const PIEZAS = [
        'portada.svg' => ['portada', 'Portada'],
        'contratapa.svg' => ['contratapa', 'Contratapa'],
        'lomo.svg' => ['lomo', 'Lomo'],
        'cubierta-completa.svg' => ['cubierta', 'Cubierta completa'],
        'sobrelomo.svg' => ['sobrelomo', 'Sobrelomo'],
    ];

    /** @return list<string> nombres de los archivos escritos */
    public static function escribir(Libro $libro, string $directorio): array
    {
        if (!is_dir($directorio) && !mkdir($directorio, 0777, true)) {
            throw new \RuntimeException("No se pudo crear la carpeta $directorio.");
        }

        // Todo se calcula antes de tocar el disco: si algo falla al dibujar, la carpeta no cambia.
        $medidas = new Medidas($libro);
        $cuerpo = Plantilla::crear($libro->plantilla)->dibujar($libro, $medidas);
        $contenidos = [];
        foreach (self::piezas($libro) as $archivo => [$vista, $nombre]) {
            $contenidos[$archivo] = Svg::documento($medidas->vista($vista), "{$libro->titulo} · $nombre", $cuerpo);
        }
        $contenidos['medidas.txt'] = self::resumen($libro, $medidas);

        // Se quitan las piezas de una escritura anterior: si el libro cambió de solapas, el sobrelomo viejo
        // no puede quedarse junto a la cubierta nueva.
        foreach ([...array_keys(self::PIEZAS), 'medidas.txt'] as $archivo) {
            self::borrar("$directorio/$archivo");
        }
        foreach ($contenidos as $archivo => $contenido) {
            self::guardar("$directorio/$archivo", $contenido);
        }

        return array_keys($contenidos);
    }

    /**
     * El sobrelomo ya incluye la cubierta completa y sus solapas, así que con solapas no se escribe la
     * cubierta completa, y sin solapas no hay sobrelomo.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private static function piezas(Libro $libro): array
    {
        $excluida = $libro->solapasMm > 0 ? 'cubierta-completa.svg' : 'sobrelomo.svg';

        return array_diff_key(self::PIEZAS, [$excluida => true]);
    }

    private static function borrar(string $ruta): void
    {
        if (is_file($ruta) && !unlink($ruta)) {
            throw new \RuntimeException("No se pudo borrar $ruta.");
        }
    }

    private static function guardar(string $ruta, string $contenido): void
    {
        if (file_put_contents($ruta, $contenido) === false) {
            throw new \RuntimeException("No se pudo escribir $ruta.");
        }
    }

    private static function resumen(Libro $libro, Medidas $medidas): string
    {
        $lineas = [
            "Libro: {$libro->titulo} — {$libro->autor}",
            'Corte: ' . self::fmt($libro->anchoMm) . ' × ' . self::fmt($libro->altoMm) . ' mm',
            'Sangrado: ' . self::fmt($libro->sangradoMm) . ' mm en cada borde exterior',
            'Lomo: ' . self::fmt($libro->lomoMm) . ' mm (confírmalo con la imprenta: depende del papel)',
            $libro->solapasMm > 0 ? 'Solapas: ' . self::fmt($libro->solapasMm) . ' mm' : 'Sin solapas',
            ...($libro->degradado === null ? [] : ['Fondo: ' . $libro->degradado->descripcion()]),
            '',
            'Tamaño de cada archivo, con sangrado (ancho × alto):',
        ];
        foreach (self::piezas($libro) as $archivo => [$vista]) {
            $zona = $medidas->vista($vista);
            $lineas[] = "  $archivo: " . self::fmt($zona->ancho) . ' × ' . self::fmt($zona->alto) . ' mm';
        }

        return implode("\n", $lineas) . "\n";
    }

    /** Milímetros con coma decimal y sin ceros sobrantes: 461,6 y 135, no 461,60 ni 135,00. */
    private static function fmt(float $mm): string
    {
        return rtrim(rtrim(number_format($mm, 2, ',', ''), '0'), ',');
    }
}
