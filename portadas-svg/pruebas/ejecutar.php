<?php

declare(strict_types=1);

/**
 * Pruebas de portadas-svg, sin dependencias. Uso: php pruebas/ejecutar.php
 */

require __DIR__ . '/../src/autocarga.php';

use PortadasSvg\Archivos;
use PortadasSvg\Cli;
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
    if ($libro->formato->solapas > 0) {
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

        Archivos::borrarDirectorio($dir);
    },

    'El título se escapa en el SVG' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        Generador::escribir(libroDeEjemplo(['titulo' => 'Ana & Luis']), $dir);
        verdadero(str_contains((string) file_get_contents("$dir/portada.svg"), 'Ana &amp; Luis'), 'el & no se escapó');
        Archivos::borrarDirectorio($dir);
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
        Archivos::borrarDirectorio($dir);
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
                    $fuera[] = "{$libro->plantilla}, solapas {$libro->formato->solapas}, lomo {$libro->formato->lomo} mm: «{$caja['texto']}»";
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
        Archivos::borrarDirectorio($dir);
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

    'Svg::e quita los caracteres que XML no admite' => function (): void {
        // El salto de página y el tabulador vertical, típicos al copiar de un PDF, se leen como espacio.
        igual('a b c', Svg::e("a\x0Cb\x0Bc"));
        igual('abc', Svg::e("a\x01b\x00c"));
        igual("a\u{FFFD}b", Svg::e("a\xFFb"));
    },

    'Un salto de página pegado en un texto no rompe los SVG' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        Generador::escribir(libroDeEjemplo(['titulo' => "Título\x0Ccon control", 'contratapa' => "Texto\x0Bcon otro", 'solapas_mm' => 90]), $dir);
        libxml_use_internal_errors(true);
        foreach (glob("$dir/*.svg") ?: [] as $archivo) {
            $xml = new DOMDocument();
            verdadero($xml->load($archivo), "$archivo no es XML válido");
        }
        Archivos::borrarDirectorio($dir);
    },

    'Los paneles de cada vista' => function (): void {
        $con = new Medidas(libroDeEjemplo(['solapas_mm' => 90]));
        igual(['contratapa', 'lomo', 'portada'], $con->paneles('cubierta'));
        igual(['solapa-contratapa', 'contratapa', 'lomo', 'portada', 'solapa-portada'], $con->paneles('sobrelomo'));
        igual(['contratapa', 'lomo', 'portada'], (new Medidas(libroDeEjemplo()))->paneles('sobrelomo'));
        lanza(InvalidArgumentException::class, fn () => $con->paneles('inexistente'));
    },

    'Cada pieza lleva solo su parte del dibujo' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        // El título sale en el <title> de todas las piezas, así que la portada se identifica por su subtítulo.
        Generador::escribir(libroDeEjemplo([
            'subtitulo' => 'SUBTITULO-X',
            'contratapa' => 'TEXTO-CONTRATAPA',
            'solapa_contratapa' => 'TEXTO-SOLAPA-C',
            'solapa_portada' => 'TEXTO-SOLAPA-P',
            'solapas_mm' => 90,
        ]), $dir);
        $esta = static fn (string $archivo, string $texto): bool => str_contains((string) file_get_contents("$dir/$archivo"), $texto);

        verdadero($esta('portada.svg', 'SUBTITULO-X'), 'la portada no lleva su subtítulo');
        verdadero(!$esta('portada.svg', 'TEXTO-CONTRATAPA') && !$esta('portada.svg', 'TEXTO-SOLAPA'), 'portada.svg arrastra otros paneles');
        verdadero($esta('contratapa.svg', 'TEXTO-CONTRATAPA'), 'la contratapa no lleva su texto');
        verdadero(!$esta('contratapa.svg', 'SUBTITULO-X') && !$esta('contratapa.svg', 'TEXTO-SOLAPA'), 'contratapa.svg arrastra otros paneles');
        verdadero(!$esta('lomo.svg', 'SUBTITULO-X') && !$esta('lomo.svg', 'TEXTO-CONTRATAPA') && !$esta('lomo.svg', 'TEXTO-SOLAPA'), 'lomo.svg arrastra otros paneles');
        foreach (['SUBTITULO-X', 'TEXTO-CONTRATAPA', 'TEXTO-SOLAPA-C', 'TEXTO-SOLAPA-P'] as $texto) {
            verdadero($esta('sobrelomo.svg', $texto), "sobrelomo.svg no lleva $texto");
        }
        Archivos::borrarDirectorio($dir);
    },

    'El CLI explica el uso y rechaza lo que no entiende' => function (): void {
        $correr = static function (array $argumentos): array {
            $salida = '';
            $errores = '';
            // Un aviso de PHP se vuelve excepción para que no se cuele sin que lo vea la prueba.
            set_error_handler(static function (int $nivel, string $mensaje): never {
                throw new ErrorException($mensaje);
            });
            try {
                $codigo = Cli::ejecutar(
                    $argumentos,
                    dirname(__DIR__),
                    static function (string $texto) use (&$salida): void {
                        $salida .= $texto;
                    },
                    static function (string $texto) use (&$errores): void {
                        $errores .= $texto;
                    },
                );
            } finally {
                restore_error_handler();
            }

            return [$codigo, $salida, $errores];
        };

        [$codigo, $salida] = $correr(['--help']);
        igual(0, $codigo);
        verdadero(str_contains($salida, 'Uso:'), '--help no imprime el uso');

        [$codigo, , $errores] = $correr(['--nada']);
        igual(2, $codigo);
        verdadero(str_contains($errores, '--nada'), 'el error no nombra la opción desconocida');

        igual(2, $correr(['--salida='])[0]);
        igual(2, $correr([])[0]);
        igual(2, $correr(['--comprobar', 'libro.json'])[0]);

        [$codigo, , $errores] = $correr(['/no/existe.json']);
        igual(1, $codigo);
        verdadero(str_contains($errores, '/no/existe.json'), 'el error no nombra el archivo');
        verdadero(!str_contains($errores, 'Failed to open'), 'se coló un aviso de PHP');
    },

    'Dos libros con el mismo nombre de archivo se rechazan sin escribir nada' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        mkdir("$dir/a", 0777, true);
        mkdir("$dir/b", 0777, true);
        file_put_contents("$dir/a/libro.json", '{"titulo":"A","autor":"X","paginas":100}');
        file_put_contents("$dir/b/libro.json", '{"titulo":"B","autor":"Y","paginas":100}');

        $errores = '';
        $codigo = Cli::ejecutar(
            ["$dir/a/libro.json", "$dir/b/libro.json", "--salida=$dir/out"],
            dirname(__DIR__),
            static function (string $texto): void {
            },
            static function (string $texto) use (&$errores): void {
                $errores .= $texto;
            },
        );

        igual(1, $codigo);
        verdadero(str_contains($errores, "$dir/a/libro.json") && str_contains($errores, "$dir/b/libro.json"), 'el error no nombra los dos archivos');
        verdadero(!is_dir("$dir/out"), 'se escribió algo antes de detectar el repetido');
        Archivos::borrarDirectorio($dir);
    },

    'El CLI comprueba los ejemplos y no deja carpetas temporales' => function (): void {
        $antes = count(glob(sys_get_temp_dir() . '/portadas-svg-*') ?: []);
        $codigo = Cli::ejecutar(['--comprobar'], dirname(__DIR__), static function (string $texto): void {
        }, static function (string $texto): void {
        });

        igual(0, $codigo);
        igual($antes, count(glob(sys_get_temp_dir() . '/portadas-svg-*') ?: []));
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
