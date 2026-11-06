<?php

declare(strict_types=1);

/** Pruebas de el texto (ajuste, escapes, geometría de la maqueta) y el SVG. Las funciones auxiliares están en ../ayudas.php. */

use PortadasSvg\Adornos\Adorno;
use PortadasSvg\Adornos\Generico;
use PortadasSvg\Adornos\Motivo;
use PortadasSvg\Anchos;
use PortadasSvg\Archivos;
use PortadasSvg\Catalogo;
use PortadasSvg\Cli;
use PortadasSvg\Color;
use PortadasSvg\Degradado;
use PortadasSvg\FactorFijo;
use PortadasSvg\Fuentes;
use PortadasSvg\Generador;
use PortadasSvg\Libro;
use PortadasSvg\Lista;
use PortadasSvg\Medidas;
use PortadasSvg\Nombres;
use PortadasSvg\Numeros;
use PortadasSvg\Plantilla;
use PortadasSvg\Rectangulo;
use PortadasSvg\Svg;
use PortadasSvg\Texto;

return [
    'Svg::n quita ceros sobrantes y el signo de -0' => function (): void {
        igual('135', Svg::n(135.0));
        igual('4.8', Svg::n(4.8));
        igual('461.6', Svg::n(461.6));
        igual('0', Svg::n(-0.0001));
    },

    'Svg::e escapa las comillas, el ángulo y el ampersand' => function (): void {
        igual('&lt;a &quot;x&quot;&gt; &amp; y', Svg::e('<a "x"> & y'));
    },

    'Texto::envolver parte por palabras y respeta los párrafos' => function (): void {
        igual(['uno', 'dos', 'tres'], Texto::envolver('uno dos tres', 5.0, 1.0, new FactorFijo(1.0)));
        igual(['a b', '', 'c'], Texto::envolver("a b\n\nc", 20.0, 1.0, new FactorFijo(1.0)));
        igual([], Texto::envolver('', 20.0, 1.0, new FactorFijo(1.0)));
    },

    'Svg::lineas conserva las líneas en blanco con un espacio de no ruptura' => function (): void {
        verdadero(str_contains(Svg::lineas(['a', '', 'b'], 0.0, 1.0, 1.35), "\u{00A0}"), 'la línea vacía no lleva espacio');
    },

    'Texto::envolver corta las palabras más largas que la línea' => function (): void {
        igual(['abc', 'def', 'gh'], Texto::envolver('abcdefgh', 3.0, 1.0, new FactorFijo(1.0)));
    },

    'Texto::ajustar baja el tamaño hasta que el texto cabe' => function (): void {
        // Una línea de «hola» a 7,4 mm ocupa 7,4 × 1,35 = 9,99 mm; a 7,6 mm ocuparía 10,26 mm.
        $ajuste = Texto::ajustar('hola', 100.0, 10.0, 10.0, 5.0, new FactorFijo(0.5), 1.35);
        aproximado(7.4, $ajuste['tamano']);
        igual(['hola'], $ajuste['lineas']);
    },

    'Texto::ajustar recorta con «…» si ni al mínimo cabe' => function (): void {
        $ajuste = Texto::ajustar('uno dos tres cuatro cinco seis', 5.0, 1.0, 1.0, 1.0, new FactorFijo(1.0), 1.35);
        aproximado(1.0, $ajuste['tamano']);
        igual(['uno…'], $ajuste['lineas']);
    },

    'Texto::unaLinea reduce el tamaño o recorta' => function (): void {
        aproximado(4.0, Texto::unaLinea('Ana', 100.0, 4.0, 2.0, new FactorFijo(0.5))['tamano']);

        $largo = Texto::unaLinea(str_repeat('a', 100), 10.0, 4.0, 2.0, new FactorFijo(0.5));
        aproximado(2.0, $largo['tamano']);
        igual(str_repeat('a', 9) . '…', $largo['texto']);
    },

    'El título se escapa en el SVG' => function (): void {
        conCarpeta(function (string $dir): void {
            Generador::escribir(libroDeEjemplo(['titulo' => 'Ana & Luis']), $dir);
            verdadero(str_contains((string) file_get_contents("$dir/portada.svg"), 'Ana &amp; Luis'), 'el & no se escapó');
        });
    },

    'Texto::unaLinea descuenta el espaciado entre letras' => function (): void {
        // Cuatro letras con 1 mm de espaciado en 10 mm: a tamaño 1,5 cada letra ocupa 1,5 + 1 = 2,5 mm.
        aproximado(1.5, Texto::unaLinea('ABCD', 10.0, 4.0, 1.0, new FactorFijo(1.0), 1.0)['tamano']);
    },

    'Ningún texto se sale de su panel, con textos largos y en las dos plantillas' => function (): void {
        $fuera = [];
        foreach (librosParaGeometria() as $libro) {
            $plantilla = Plantilla::crear($libro->plantilla);
            $medidas = new Medidas($libro);
            foreach (cajasDeTexto($plantilla->dibujar($libro, $medidas), $plantilla) as $caja) {
                $panel = panelDe($medidas, $caja, $libro);
                if ($panel === null || !dentro($caja, $panel)) {
                    $fuera[] = "{$libro->plantilla}, solapas {$libro->formato->solapas}, lomo {$libro->formato->lomo} mm: «{$caja['texto']}»";
                }
            }
        }
        verdadero($fuera === [], "textos fuera de su panel:\n  " . implode("\n  ", array_slice($fuera, 0, 8)));
    },

    'El subtítulo deja bajo el título un espacio proporcional a su tamaño' => function (): void {
        $apretados = [];
        foreach (['clasica', 'sobria', 'tipografica'] as $plantilla) {
            foreach (['Hola', 'Apología de Sócrates', 'Diálogos de Platón sobre la justicia, el alma, el amor y la ciudad ideal, con notas'] as $titulo) {
                $libro = libroDeEjemplo(['plantilla' => $plantilla, 'titulo' => $titulo, 'subtitulo' => 'SUBTITULO-PRUEBA']);
                $plantillaObjeto = Plantilla::crear($plantilla);
                $cajas = array_filter(
                    cajasDeTexto($plantillaObjeto->dibujar($libro, new Medidas($libro), 'portada'), $plantillaObjeto),
                    static fn (array $caja): bool => !$caja['girado'],
                );
                $tituloCaja = null;
                $subtituloCaja = null;
                foreach ($cajas as $caja) {
                    if (str_contains($caja['texto'], 'SUBTITULO-PRUEBA')) {
                        $subtituloCaja = $caja;
                    } elseif (str_contains($caja['texto'], explode(' ', $titulo)[0])) {
                        $tituloCaja = $caja;
                    }
                }
                if ($tituloCaja === null || $subtituloCaja === null) {
                    throw new RuntimeException("no se encontró el título o el subtítulo de «{$titulo}»");
                }
                $espacio = $subtituloCaja['y0'] - $tituloCaja['y1'];
                if ($espacio < 0.3 * $tituloCaja['tamano'] - 1e-6) {
                    $apretados[] = sprintf('%s, «%s»: %.1f mm bajo un título de %.1f mm', $plantilla, $titulo, $espacio, $tituloCaja['tamano']);
                }
            }
        }
        verdadero($apretados === [], "subtítulo demasiado cerca del título:\n  " . implode("\n  ", $apretados));
    },

    'Svg::e quita los caracteres que XML no admite' => function (): void {
        // El salto de página y el tabulador vertical, típicos al copiar de un PDF, se leen como espacio.
        igual('a b c', Svg::e("a\x0Cb\x0Bc"));
        igual('abc', Svg::e("a\x01b\x00c"));
        igual("a\u{FFFD}b", Svg::e("a\xFFb"));
    },

    'Un salto de página pegado en un texto no rompe los SVG' => function (): void {
        conCarpeta(function (string $dir): void {
            Generador::escribir(libroDeEjemplo(['titulo' => "Título\x0Ccon control", 'contratapa' => "Texto\x0Bcon otro", 'solapas_mm' => 90]), $dir);
            libxml_use_internal_errors(true);
            foreach (glob("$dir/*.svg") ?: [] as $archivo) {
                $xml = new DOMDocument();
                verdadero($xml->load($archivo), "$archivo no es XML válido");
            }
        });
    },

    'El título se reparte en líneas parejas sin cambiar cuántas son' => function (): void {
        // Llenando cada línea hasta el borde saldría «El arte de la» y «guerra».
        $medida = new FactorFijo(1.0);
        igual(['El arte de la', 'guerra'], Texto::envolver('El arte de la guerra', 14.0, 1.0, $medida));
        igual(['El arte de', 'la guerra'], Texto::ajustar('El arte de la guerra', 14.0, 10.0, 1.0, 1.0, $medida, 1.35, true)['lineas']);
        // Con una sola línea, o sin pedir el reparto, no cambia nada.
        igual(['Hola'], Texto::ajustar('Hola', 14.0, 10.0, 1.0, 1.0, $medida, 1.35, true)['lineas']);
        igual(['El arte de la', 'guerra'], Texto::ajustar('El arte de la guerra', 14.0, 10.0, 1.0, 1.0, $medida, 1.35)['lineas']);
    },

    'Cada SVG se anuncia como imagen con título, descripción e idioma' => function (): void {
        conCarpeta(function (string $dir): void {
            Generador::escribir(libroDeEjemplo(['subtitulo' => 'Sub', 'contratapa' => "Primer párrafo.\n\nSegundo   párrafo.", 'idioma' => 'pt-BR']), "$dir/libro");
            foreach (['portada', 'contratapa', 'lomo', 'cubierta-completa'] as $pieza) {
                $doc = new DOMDocument();
                verdadero($doc->load("$dir/libro/$pieza.svg"), "$pieza no es XML válido");
                $raiz = $doc->documentElement ?? throw new RuntimeException("$pieza no tiene raíz");
                igual('img', $raiz->getAttribute('role'));
                igual('titulo descripcion', $raiz->getAttribute('aria-labelledby'));
                igual('pt-BR', $raiz->getAttribute('lang'));
                igual('pt-BR', $raiz->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
                verdadero(str_contains(textoDe($doc, 'title'), 'Título de prueba'), 'el título no nombra el libro');
                verdadero(str_contains(textoDe($doc, 'desc'), '«Título de prueba»: Sub, de Autor de prueba.'), "la descripción de $pieza no dice de qué libro es");
            }
            $desc = new DOMDocument();
            $desc->load("$dir/libro/contratapa.svg");
            verdadero(str_contains(textoDe($desc, 'desc'), 'Primer párrafo. Segundo párrafo.'), 'la descripción de la contratapa no lleva su texto');
            lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['idioma' => 'español']));
            igual('es', libroDeEjemplo()->idioma);
        });
    },
];
