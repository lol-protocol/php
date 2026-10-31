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

    /**
     * Con `$textoATrazos`, el texto sale como contornos en vez de con la fuente incrustada (ver Plantilla).
     *
     * @return list<string> nombres de los archivos escritos
     */
    public static function escribir(Libro $libro, string $directorio, bool $textoATrazos = false): array
    {
        if (!is_dir($directorio) && !mkdir($directorio, 0777, true)) {
            throw new \RuntimeException("No se pudo crear la carpeta $directorio.");
        }

        // Todo se calcula antes de tocar el disco: si algo falla al dibujar, la carpeta no cambia.
        $medidas = new Medidas($libro);
        $plantilla = Plantilla::crear($libro->plantilla);
        $plantilla->textoATrazos = $textoATrazos;
        $contenidos = [];
        foreach (self::piezas($libro) as $archivo => [$vista, $nombre]) {
            $cuerpo = $plantilla->dibujar($libro, $medidas, $vista);
            $contenidos[$archivo] = Svg::documento($medidas->vista($vista), "{$libro->textos->titulo} · $nombre", self::descripcion($libro, $vista, $nombre), $libro->idioma, $cuerpo);
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
     * Lo que dice una pieza, para quien no la ve: qué pieza es, de qué libro y, en la contratapa, su texto.
     * Se recorta a 500 letras.
     */
    private static function descripcion(Libro $libro, string $vista, string $nombre): string
    {
        $t = $libro->textos;
        $libroDe = '«' . $t->titulo . '»' . ($t->subtitulo === '' ? '' : ": {$t->subtitulo}") . ', de ' . $t->autor . '.';
        $texto = match ($vista) {
            'contratapa', 'cubierta', 'sobrelomo' => ($t->cita === '' ? '' : "«{$t->cita}» ") . $t->contratapa,
            default => '',
        };
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        if (mb_strlen($texto) > 500) {
            $texto = rtrim(mb_substr($texto, 0, 499)) . '…';
        }

        return trim("$nombre del libro $libroDe $texto");
    }

    /**
     * Cosas que conviene mirar de un libro antes de publicarlo: letras de sus textos que las fuentes
     * incrustadas no tienen (ver Fuentes: en el visor se dibujan con otra fuente, y el ajuste del texto las mide
     * con un ancho medio) y el derecho de autor de una traducción.
     *
     * @return list<string> un aviso por texto afectado
     */
    public static function avisos(Libro $libro, bool $textoATrazos = false): array
    {
        $medida = Plantilla::crear($libro->plantilla)->medidaDe();
        $t = $libro->textos;
        $textos = [
            'El título' => $t->titulo, 'El subtítulo' => $t->subtitulo, 'El autor' => mb_strtoupper($t->autor), 'El traductor' => $t->traductor,
            'La editorial' => $t->editorial, 'La colección' => $t->coleccion, 'La contratapa' => $t->contratapa,
            'La solapa de la contratapa' => $t->solapaContratapa, 'La solapa de la portada' => $t->solapaPortada,
        ];
        $avisos = [];
        foreach ($textos as $nombre => $texto) {
            if ($textoATrazos) {
                $sin = $medida->sinContorno($texto);
                if ($sin !== '') {
                    $avisos[] = "$nombre lleva letras que ni las fuentes ni las fuentes extra tienen ($sin); no se dibujan. Ver herramientas/glifos-extra.py.";
                }
                continue;
            }
            $faltan = $medida->noCubiertas($texto);
            if ($faltan !== '') {
                $conExtra = implode('', array_diff(mb_str_split($faltan), mb_str_split($medida->sinContorno($texto))));
                $aviso = "$nombre lleva letras que la fuente no tiene ($faltan); se verán con otra fuente.";
                $avisos[] = $conExtra === '' ? $aviso : "$aviso Con --trazos se dibujan ($conExtra) con las fuentes extra.";
            }
        }

        if ($t->traductor !== '') {
            $avisos[] = "El traductor «{$t->traductor}» figura en la cubierta: una traducción moderna puede no ser de dominio público; comprueba su licencia.";
        }

        return $avisos;
    }

    /**
     * El sobrelomo ya incluye la cubierta completa y sus solapas, así que con solapas no se escribe la
     * cubierta completa, y sin solapas no hay sobrelomo.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private static function piezas(Libro $libro): array
    {
        $excluida = $libro->formato->solapas > 0 ? 'cubierta-completa.svg' : 'sobrelomo.svg';

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
            "Libro: {$libro->textos->titulo} — {$libro->textos->autor}",
            'Corte: ' . self::fmt($libro->formato->ancho) . ' × ' . self::fmt($libro->formato->alto) . ' mm',
            'Sangrado: ' . self::fmt($libro->formato->sangrado) . ' mm en cada borde exterior',
            'Lomo: ' . self::fmt($libro->formato->lomo) . ' mm (confírmalo con la imprenta: depende del papel)',
            $libro->formato->solapas > 0 ? 'Solapas: ' . self::fmt($libro->formato->solapas) . ' mm' : 'Sin solapas',
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
