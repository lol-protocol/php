<?php

declare(strict_types=1);

/**
 * Pruebas de portadas-svg, sin dependencias. Uso: php pruebas/ejecutar.php
 */

require __DIR__ . '/../src/autocarga.php';

use PortadasSvg\Color;
use PortadasSvg\Degradado;
use PortadasSvg\Generador;
use PortadasSvg\Libro;
use PortadasSvg\Medidas;
use PortadasSvg\Plantilla;
use PortadasSvg\Rectangulo;
use PortadasSvg\Svg;
use PortadasSvg\Texto;

function igual(mixed $esperado, mixed $obtenido): void
{
    if ($esperado !== $obtenido) {
        throw new RuntimeException('esperado ' . var_export($esperado, true) . ', obtenido ' . var_export($obtenido, true));
    }
}

function aproximado(float $esperado, float $obtenido): void
{
    if (abs($esperado - $obtenido) > 1e-6) {
        throw new RuntimeException("esperado $esperado, obtenido $obtenido");
    }
}

function verdadero(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function lanza(string $clase, callable $cuerpo): void
{
    try {
        $cuerpo();
    } catch (Throwable $error) {
        if ($error instanceof $clase) {
            return;
        }
        throw new RuntimeException('lanzó ' . $error::class . ': ' . $error->getMessage(), 0, $error);
    }
    throw new RuntimeException("no lanzó $clase");
}

function libroDeEjemplo(array $cambios = []): Libro
{
    return Libro::desdeArray('prueba', $cambios + ['titulo' => 'Título de prueba', 'autor' => 'Autor de prueba', 'paginas' => 112]);
}

function borrarDirectorio(string $directorio): void
{
    foreach (glob("$directorio/*") ?: [] as $ruta) {
        if (is_dir($ruta)) {
            borrarDirectorio($ruta);
        } else {
            unlink($ruta);
        }
    }
    rmdir($directorio);
}

/** Libro con todos los textos largos, para forzar los ajustes. */
function libroLargo(string $plantilla, float $solapas, array $extra = []): Libro
{
    return Libro::desdeArray('largo', $extra + [
        'titulo' => 'Diálogos de Platón sobre la justicia, el alma, el amor y la ciudad ideal, con notas',
        'subtitulo' => 'Edición con un subtítulo bastante largo para forzar el ajuste',
        'autor' => 'Marco Tulio Cicerón Aureliano de la Academia Nueva y Antigua',
        'traductor' => 'Traductor con un nombre muy largo y varias palabras de más',
        'editorial' => 'Edición de ejemplo para la colección de textos clásicos de dominio público',
        'coleccion' => 'Colección de prueba muy larga',
        'isbn' => '978-84-00-00000-0',
        'contratapa' => str_repeat('Párrafo de prueba con bastante texto para forzar el ajuste de la contratapa. ', 30),
        'solapa_contratapa' => str_repeat('Solapa con texto largo. ', 40),
        'solapa_portada' => str_repeat('Solapa de la portada con texto largo. ', 40),
        'paginas' => 112,
        'solapas_mm' => $solapas,
        'plantilla' => $plantilla,
    ]);
}

/** Ancho estimado de una línea, con las mismas estimaciones que usan las plantillas. */
function anchoEstimado(string $texto, float $tamano, float $espaciado, Plantilla $plantilla): float
{
    return mb_strlen($texto) * ($tamano * $plantilla->factorPara($texto) + $espaciado);
}

/**
 * Caja estimada de cada bloque de texto del dibujo. Un bloque es un `<text>`: sus líneas son los `tspan`.
 * El lomo, girado, es una sola línea en vertical.
 *
 * @return list<array{texto: string, x0: float, x1: float, y0: float, y1: float}>
 */
function cajasDeTexto(string $cuerpo, Plantilla $plantilla): array
{
    $doc = new DOMDocument();
    verdadero($doc->loadXML('<svg xmlns="http://www.w3.org/2000/svg">' . $cuerpo . '</svg>'), 'el dibujo no es XML válido');

    $cajas = [];
    foreach ($doc->getElementsByTagName('text') as $elemento) {
        $tamano = (float) $elemento->getAttribute('font-size');
        $espaciado = (float) $elemento->getAttribute('letter-spacing');

        if ($elemento->getAttribute('transform') !== '') {
            preg_match('/translate\((-?[\d.]+) (-?[\d.]+)\) rotate\((-?\d+)\)/', $elemento->getAttribute('transform'), $giro);
            $x = (float) $giro[1];
            $y = (float) $giro[2];
            $largo = anchoEstimado($elemento->textContent, $tamano, $espaciado, $plantilla);
            // Con rotate(90) las letras crecen hacia +x; con rotate(-90), hacia -x.
            $hacia = (int) $giro[3] === 90 ? 0.8 : 0.2;
            $cajas[] = [
                'texto' => $elemento->textContent,
                'x0' => $x - (1 - $hacia) * $tamano,
                'x1' => $x + $hacia * $tamano,
                'y0' => $y - $largo / 2,
                'y1' => $y + $largo / 2,
            ];
            continue;
        }

        $anclaje = $elemento->getAttribute('text-anchor') ?: 'start';
        $base = (float) $elemento->getAttribute('y');
        $caja = ['texto' => [], 'x0' => INF, 'x1' => -INF, 'y0' => INF, 'y1' => -INF];
        foreach ($elemento->getElementsByTagName('tspan') as $tspan) {
            $base += (float) $tspan->getAttribute('dy');
            $texto = str_replace("\u{00A0}", '', $tspan->textContent);
            if ($texto === '') {
                continue;
            }
            $x = (float) $tspan->getAttribute('x');
            $ancho = anchoEstimado($texto, $tamano, $espaciado, $plantilla);
            [$izquierda, $derecha] = match ($anclaje) {
                'middle' => [$x - $ancho / 2, $x + $ancho / 2],
                'end' => [$x - $ancho, $x],
                default => [$x, $x + $ancho],
            };
            $caja['texto'][] = $texto;
            $caja['x0'] = min($caja['x0'], $izquierda);
            $caja['x1'] = max($caja['x1'], $derecha);
            $caja['y0'] = min($caja['y0'], $base - 0.8 * $tamano);
            $caja['y1'] = max($caja['y1'], $base + 0.2 * $tamano);
        }
        if ($caja['texto'] !== []) {
            $caja['texto'] = implode(' / ', $caja['texto']);
            $cajas[] = $caja;
        }
    }

    return $cajas;
}

/** Panel de corte donde cae el centro horizontal de una caja, o null si no cae en ninguno. */
function panelDe(Medidas $medidas, array $caja, Libro $libro): ?Rectangulo
{
    $centro = ($caja['x0'] + $caja['x1']) / 2;
    $nombres = ['contratapa', 'lomo', 'portada'];
    if ($libro->solapasMm > 0) {
        $nombres[] = 'solapa-contratapa';
        $nombres[] = 'solapa-portada';
    }
    foreach ($nombres as $nombre) {
        $panel = $medidas->pieza($nombre);
        if ($centro >= $panel->x && $centro <= $panel->x + $panel->ancho) {
            return $panel;
        }
    }

    return null;
}

/** Si la caja cabe entera dentro del panel de corte. */
function dentro(array $caja, Rectangulo $panel): bool
{
    return $caja['x0'] >= $panel->x - 1e-6 && $caja['x1'] <= $panel->x + $panel->ancho + 1e-6
        && $caja['y0'] >= $panel->y - 1e-6 && $caja['y1'] <= $panel->y + $panel->alto + 1e-6;
}

$pruebas = [
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
        igual(['uno', 'dos', 'tres'], Texto::envolver('uno dos tres', 5.0, 1.0, 1.0));
        igual(['a b', '', 'c'], Texto::envolver("a b\n\nc", 20.0, 1.0, 1.0));
        igual([], Texto::envolver('', 20.0, 1.0, 1.0));
    },

    'Svg::lineas conserva las líneas en blanco con un espacio de no ruptura' => function (): void {
        verdadero(str_contains(Svg::lineas(['a', '', 'b'], 0.0, 1.0, 1.35), "\u{00A0}"), 'la línea vacía no lleva espacio');
    },

    'Texto::envolver corta las palabras más largas que la línea' => function (): void {
        igual(['abc', 'def', 'gh'], Texto::envolver('abcdefgh', 3.0, 1.0, 1.0));
    },

    'Texto::ajustar baja el tamaño hasta que el texto cabe' => function (): void {
        // Una línea de «hola» a 7,4 mm ocupa 7,4 × 1,35 = 9,99 mm; a 7,6 mm ocuparía 10,26 mm.
        $ajuste = Texto::ajustar('hola', 100.0, 10.0, 10.0, 5.0, 0.5, 1.35);
        aproximado(7.4, $ajuste['tamano']);
        igual(['hola'], $ajuste['lineas']);
    },

    'Texto::ajustar recorta con «…» si ni al mínimo cabe' => function (): void {
        $ajuste = Texto::ajustar('uno dos tres cuatro cinco seis', 5.0, 1.0, 1.0, 1.0, 1.0, 1.35);
        aproximado(1.0, $ajuste['tamano']);
        igual(['un…'], $ajuste['lineas']);
    },

    'Texto::unaLinea reduce el tamaño o recorta' => function (): void {
        aproximado(4.0, Texto::unaLinea('Ana', 100.0, 4.0, 2.0, 0.5)['tamano']);

        $largo = Texto::unaLinea(str_repeat('a', 100), 10.0, 4.0, 2.0, 0.5);
        aproximado(2.0, $largo['tamano']);
        igual(str_repeat('a', 9) . '…', $largo['texto']);
    },

    'Medidas de cubierta con solapas (sobrelomo)' => function (): void {
        $medidas = new Medidas(libroDeEjemplo(['solapas_mm' => 90]));
        aproximado(461.6, $medidas->anchoTotal); // 2·3 + 2·90 + 2·135 + 5,6
        aproximado(216.0, $medidas->altoTotal);
        aproximado(233.6, $medidas->pieza('portada')->x); // 3 + 90 + 135 + 5,6
        aproximado(275.6, $medidas->vista('cubierta')->ancho); // contratapa, lomo y portada, sin solapas
        aproximado(461.6, $medidas->vista('sobrelomo')->ancho);
    },

    'Medidas sin solapas: el sangrado queda en el borde exterior' => function (): void {
        $medidas = new Medidas(libroDeEjemplo(['paginas' => 96]));
        aproximado(280.8, $medidas->anchoTotal); // 2·3 + 2·135 + 4,8
        aproximado(0.0, $medidas->vista('contratapa')->x);
        aproximado(138.0, $medidas->vista('contratapa')->ancho); // 3 de sangrado + 135
        aproximado(138.0, $medidas->vista('portada')->ancho);
        aproximado(280.8, $medidas->vista('portada')->x + $medidas->vista('portada')->ancho);
    },

    'El lomo se puede fijar a mano' => function (): void {
        $medidas = new Medidas(libroDeEjemplo(['lomo_mm' => 7]));
        aproximado(283.0, $medidas->anchoTotal);
    },

    'Libro rechaza los datos que no tienen sentido' => function (): void {
        lanza(InvalidArgumentException::class, fn () => Libro::desdeArray('x', ['autor' => 'A', 'paginas' => 10]));
        lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['plantilla' => 'inexistente']));
        lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['colores' => ['fondo' => '#xyz']]));
        lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['colores' => ['borde' => '#000000']]));
        lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['solapas_mm' => 20]));
        lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['paginas' => null]));
        lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['lomo_direccion' => 'raro']));
    },

    'Plantilla::crear rechaza una plantilla que no existe' => function (): void {
        lanza(InvalidArgumentException::class, fn () => Plantilla::crear('inexistente'));
    },

    'Generador escribe SVG bien formados, y el sobrelomo solo con solapas' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));

        $sin = Generador::escribir(libroDeEjemplo(), "$dir/sin");
        igual(['portada.svg', 'contratapa.svg', 'lomo.svg', 'cubierta-completa.svg', 'medidas.txt'], $sin);

        $con = Generador::escribir(libroDeEjemplo(['solapas_mm' => 90]), "$dir/con");
        igual(['portada.svg', 'contratapa.svg', 'lomo.svg', 'sobrelomo.svg', 'medidas.txt'], $con);
        foreach (glob("$dir/con/*.svg") ?: [] as $archivo) {
            $xml = new DOMDocument();
            verdadero($xml->loadXML((string) file_get_contents($archivo)), "$archivo no es XML válido");
        }

        borrarDirectorio($dir);
    },

    'El título se escapa en el SVG' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        Generador::escribir(libroDeEjemplo(['titulo' => 'Ana & Luis']), $dir);
        verdadero(str_contains((string) file_get_contents("$dir/portada.svg"), 'Ana &amp; Luis'), 'el & no se escapó');
        borrarDirectorio($dir);
    },

    'Color mezcla y decide si un color es claro' => function (): void {
        igual('#666666', Color::mezclar('#000000', '#ffffff', 0.4));
        igual('#000000', Color::mezclar('#000000', '#ffffff', 0.0));
        verdadero(Color::esClaro('#f3ead7'), 'el crema debería ser claro');
        verdadero(!Color::esClaro('#1c1f24'), 'el carbón no debería ser claro');
    },

    'Color rechaza lo que no es #rrggbb' => function (): void {
        lanza(InvalidArgumentException::class, fn () => Color::rgb('#abc'));
        lanza(InvalidArgumentException::class, fn () => Color::rgb('rojo'));
    },

    'Degradado lineal cruza el lienzo de borde a borde' => function (): void {
        $degradado = Degradado::desdeArray(['angulo' => 0, 'colores' => ['#000000', '#ffffff']], '#000000', '#111111');
        $defs = $degradado->defs(new Rectangulo(0.0, 0.0, 100.0, 50.0));
        verdadero(str_contains($defs, 'x1="0" y1="25" x2="100" y2="25"'), "extremos mal: $defs");
        verdadero(str_contains($defs, 'offset="0" stop-color="#000000"') && str_contains($defs, 'offset="1" stop-color="#ffffff"'), "paradas mal: $defs");
    },

    'Degradado radial llega a la esquina con el último color' => function (): void {
        $degradado = Degradado::desdeArray(['tipo' => 'radial', 'colores' => ['#ffffff', '#000000']], '#ffffff', '#111111');
        $defs = $degradado->defs(new Rectangulo(0.0, 0.0, 100.0, 50.0));
        verdadero(str_contains($defs, 'cx="50" cy="25" r="55.902"'), "radio mal: $defs");
    },

    'Degradado «auto» se aleja del color de texto' => function (): void {
        // Texto oscuro: el fondo se aclara. Texto claro: el fondo se oscurece.
        igual(['#000000', '#666666'], Degradado::desdeArray([], '#000000', '#111111')->colores);
        igual(['#ffffff', '#999999'], Degradado::desdeArray([], '#ffffff', '#ffffff')->colores);
    },

    'Degradado rechaza tipo, ángulo y colores no válidos' => function (): void {
        lanza(InvalidArgumentException::class, fn () => Degradado::desdeArray(['tipo' => 'cónico'], '#ffffff', '#000000'));
        lanza(InvalidArgumentException::class, fn () => Degradado::desdeArray(['angulo' => 'x'], '#ffffff', '#000000'));
        lanza(InvalidArgumentException::class, fn () => Degradado::desdeArray(['colores' => ['#000000']], '#ffffff', '#000000'));
        lanza(InvalidArgumentException::class, fn () => Degradado::desdeArray(['colores' => ['#000000', 'rojo']], '#ffffff', '#000000'));
        lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['degradado' => ['tipo' => 'cónico']]));
    },

    'Con degradado, cada pieza lo referencia y no lleva HTML ni JS' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        Generador::escribir(libroDeEjemplo(['degradado' => ['angulo' => 90]]), $dir);
        foreach (glob("$dir/*.svg") ?: [] as $archivo) {
            $svg = (string) file_get_contents($archivo);
            verdadero(str_contains($svg, '<linearGradient'), "$archivo sin degradado");
            verdadero(str_contains($svg, 'fill="url(#fondo-degradado)"'), "$archivo no usa el degradado");
            verdadero(!str_contains($svg, '<script') && !str_contains($svg, '<html'), "$archivo lleva HTML o JS");
        }
        verdadero(str_contains((string) file_get_contents("$dir/medidas.txt"), 'Fondo: degradado lineal, 90°'), 'medidas.txt no describe el fondo');
        borrarDirectorio($dir);
    },

    'El proyecto no tiene HTML ni JS' => function (): void {
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));
        $prohibidos = [];
        foreach ($archivos as $archivo) {
            if (in_array(strtolower($archivo->getExtension()), ['html', 'htm', 'js', 'mjs', 'cjs'], true)) {
                $prohibidos[] = $archivo->getPathname();
            }
        }
        igual([], $prohibidos);
    },

    'Texto::unaLinea descuenta el espaciado entre letras' => function (): void {
        // Cuatro letras con 1 mm de espaciado en 10 mm: a tamaño 1,5 cada letra ocupa 1,5 + 1 = 2,5 mm.
        aproximado(1.5, Texto::unaLinea('ABCD', 10.0, 4.0, 1.0, 1.0, 1.0)['tamano']);
    },

    'Ningún texto se sale de su panel, con textos largos y en las dos plantillas' => function (): void {
        $libros = [];
        foreach (['clasica', 'sobria'] as $plantilla) {
            foreach ([0.0, 90.0] as $solapas) {
                $libros[] = libroLargo($plantilla, $solapas);
                $libros[] = libroLargo($plantilla, $solapas, ['lomo_mm' => 3.2]);
            }
        }
        foreach (glob(__DIR__ . '/../ejemplos/*.json') ?: [] as $ruta) {
            $libros[] = Libro::desdeArchivo($ruta);
        }

        $fuera = [];
        foreach ($libros as $libro) {
            $plantilla = Plantilla::crear($libro->plantilla);
            $medidas = new Medidas($libro);
            foreach (cajasDeTexto($plantilla->dibujar($libro, $medidas), $plantilla) as $caja) {
                $panel = panelDe($medidas, $caja, $libro);
                if ($panel === null || !dentro($caja, $panel)) {
                    $fuera[] = "{$libro->plantilla}, solapas {$libro->solapasMm}, lomo {$libro->lomoMm} mm: «{$caja['texto']}»";
                }
            }
        }
        verdadero($fuera === [], "textos fuera de su panel:\n  " . implode("\n  ", array_slice($fuera, 0, 8)));
    },

    'Escribir de nuevo quita las piezas que ya no tocan' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        Generador::escribir(libroDeEjemplo(['solapas_mm' => 90]), $dir);
        $sinSolapas = Generador::escribir(libroDeEjemplo(), $dir);

        igual(['portada.svg', 'contratapa.svg', 'lomo.svg', 'cubierta-completa.svg', 'medidas.txt'], $sinSolapas);
        verdadero(!is_file("$dir/sobrelomo.svg"), 'sigue en la carpeta el sobrelomo de la escritura anterior');
        verdadero(!str_contains((string) file_get_contents("$dir/medidas.txt"), 'sobrelomo.svg'), 'medidas.txt sigue listando el sobrelomo');
        borrarDirectorio($dir);
    },

    'Un JSON roto nombra el archivo que falla' => function (): void {
        $ruta = sys_get_temp_dir() . '/portadas-svg-roto-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($ruta, '{"titulo": "x",}');
        try {
            Libro::desdeArchivo($ruta);
            throw new RuntimeException('no lanzó con un JSON roto');
        } catch (InvalidArgumentException $error) {
            verdadero(str_contains($error->getMessage(), $ruta), 'el mensaje no nombra el archivo: ' . $error->getMessage());
        } finally {
            unlink($ruta);
        }
    },

    'Los libros de ejemplo se cargan' => function (): void {
        $ejemplos = glob(__DIR__ . '/../ejemplos/*.json') ?: [];
        verdadero(count($ejemplos) >= 2, 'faltan ejemplos');
        foreach ($ejemplos as $ruta) {
            Libro::desdeArchivo($ruta);
        }
    },
];

$fallidas = 0;
foreach ($pruebas as $nombre => $cuerpo) {
    try {
        $cuerpo();
        echo "ok     $nombre\n";
    } catch (Throwable $error) {
        $fallidas++;
        echo "FALLO  $nombre\n       " . $error->getMessage() . "\n";
    }
}

echo count($pruebas) - $fallidas . ' de ' . count($pruebas) . " pruebas pasan.\n";
exit($fallidas === 0 ? 0 : 1);
