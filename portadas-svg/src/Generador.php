<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Escribe las piezas de un libro en una carpeta: un SVG por pieza y un resumen de medidas para la imprenta. */
final class Generador
{
    /** Archivo => [vista, nombre legible]. El sobrelomo solo existe si el libro tiene solapas. */
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

        $medidas = new Medidas($libro);
        $cuerpo = Plantilla::crear($libro->plantilla)->dibujar($libro, $medidas);

        $escritos = [];
        foreach (self::piezas($libro) as $archivo => [$vista, $nombre]) {
            self::guardar("$directorio/$archivo", Svg::documento($medidas->vista($vista), "{$libro->titulo} · $nombre", $cuerpo));
            $escritos[] = $archivo;
        }
        self::guardar("$directorio/medidas.txt", self::resumen($libro, $medidas));

        return [...$escritos, 'medidas.txt'];
    }

    /** @return array<string, array{0: string, 1: string}> */
    private static function piezas(Libro $libro): array
    {
        return $libro->solapasMm > 0 ? self::PIEZAS : array_diff_key(self::PIEZAS, ['sobrelomo.svg' => true]);
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
