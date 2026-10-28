<?php

declare(strict_types=1);

/**
 * Pruebas de portadas-svg, sin dependencias. Uso: php pruebas/ejecutar.php
 */

require __DIR__ . '/../src/autocarga.php';

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

/** Texto de la primera etiqueta de ese nombre en un documento XML, o vacío si no hay. */
function textoDe(DOMDocument $doc, string $etiqueta): string
{
    $nodo = $doc->getElementsByTagName($etiqueta)->item(0);

    return $nodo === null ? '' : $nodo->textContent;
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

/**
 * Libro mínimo válido, con los cambios que pida cada prueba.
 *
 * @param array<string, mixed> $cambios
 */
function libroDeEjemplo(array $cambios = []): Libro
{
    return Libro::desdeArray('prueba', $cambios + ['titulo' => 'Título de prueba', 'autor' => 'Autor de prueba', 'paginas' => 112]);
}

/**
 * Libro con todos los textos largos, para forzar los ajustes.
 *
 * @param array<string, mixed> $extra
 */
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

/**
 * Ancho estimado de una línea con la tabla de anchos de la cara con que se pinta, la misma que usan
 * las plantillas. `$extra` son los atributos font-style y font-weight del texto.
 *
 * @param array<string, float|int|string> $extra
 */
function anchoEstimado(string $texto, float $tamano, float $espaciado, Plantilla $plantilla, array $extra = []): float
{
    return $plantilla->medidaDe($extra)->em($texto) * $tamano + $espaciado * mb_strlen($texto);
}

/**
 * Los atributos de cara tipográfica de un `<text>`, en la forma que entiende `Plantilla::medidaDe`.
 *
 * @return array<string, string>
 */
function caraDe(DOMElement $elemento): array
{
    $cara = [];
    foreach (['font-style', 'font-weight'] as $atributo) {
        if ($elemento->getAttribute($atributo) !== '') {
            $cara[$atributo] = $elemento->getAttribute($atributo);
        }
    }

    return $cara;
}

/**
 * Caja estimada de cada bloque de texto del dibujo. Un bloque es un `<text>`: sus líneas son los `tspan`.
 * El lomo, girado, es una sola línea en vertical.
 *
 * @return list<array{texto: string, tamano: float, girado: bool, x0: float, x1: float, y0: float, y1: float}>
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
            if (preg_match('/translate\((-?[\d.]+) (-?[\d.]+)\) rotate\((-?\d+)\)/', $elemento->getAttribute('transform'), $giro) !== 1) {
                throw new RuntimeException('el transform del lomo no tiene el formato esperado: ' . $elemento->getAttribute('transform'));
            }
            $x = (float) $giro[1];
            $y = (float) $giro[2];
            $largo = anchoEstimado($elemento->textContent, $tamano, $espaciado, $plantilla, caraDe($elemento));
            // Con rotate(90) las letras crecen hacia +x; con rotate(-90), hacia -x.
            $hacia = (int) $giro[3] === 90 ? 0.8 : 0.2;
            $cajas[] = [
                'texto' => $elemento->textContent,
                'tamano' => $tamano,
                'girado' => true,
                'x0' => $x - (1 - $hacia) * $tamano,
                'x1' => $x + $hacia * $tamano,
                'y0' => $y - $largo / 2,
                'y1' => $y + $largo / 2,
            ];
            continue;
        }

        $anclaje = $elemento->getAttribute('text-anchor') ?: 'start';
        $base = (float) $elemento->getAttribute('y');
        $caja = ['texto' => [], 'tamano' => $tamano, 'girado' => false, 'x0' => INF, 'x1' => -INF, 'y0' => INF, 'y1' => -INF];
        foreach ($elemento->getElementsByTagName('tspan') as $tspan) {
            $base += (float) $tspan->getAttribute('dy');
            $texto = str_replace("\u{00A0}", '', $tspan->textContent);
            if ($texto === '') {
                continue;
            }
            $x = (float) $tspan->getAttribute('x');
            $ancho = anchoEstimado($texto, $tamano, $espaciado, $plantilla, caraDe($elemento));
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

/**
 * Panel de corte donde cae el centro horizontal de una caja, o null si no cae en ninguno.
 *
 * @param array{x0: float, x1: float, y0: float, y1: float} $caja
 */
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

/**
 * Si la caja cabe entera dentro del panel de corte.
 *
 * @param array{x0: float, x1: float, y0: float, y1: float} $caja
 */
function dentro(array $caja, Rectangulo $panel): bool
{
    return $caja['x0'] >= $panel->x - 1e-6 && $caja['x1'] <= $panel->x + $panel->ancho + 1e-6
        && $caja['y0'] >= $panel->y - 1e-6 && $caja['y1'] <= $panel->y + $panel->alto + 1e-6;
}

/**
 * Los libros con los que se comprueba la disposición: el de textos largos en las dos plantillas, con y sin
 * solapas y con un lomo muy estrecho; los mismos con un título muy corto, y los ejemplos del repositorio.
 *
 * @return list<Libro>
 */
function librosParaGeometria(): array
{
    $libros = [];
    foreach (['clasica', 'sobria'] as $plantilla) {
        foreach ([0.0, 90.0] as $solapas) {
            // Sin tradición y con cada una de las que tienen adorno propio.
            foreach ([null, ...array_column(Catalogo::tradiciones(), 'slug')] as $indice => $tradicion) {
                // Cada libro lleva además una categoría distinta, para que los 27 motivos pasen por la contratapa.
                $categoria = ['categoria' => Catalogo::categorias()[$indice % 27]['slug']];
                $propios = ($tradicion === null ? [] : ['tradicion' => $tradicion]) + $categoria;
                $libros[] = libroLargo($plantilla, $solapas, $propios);
                $libros[] = libroLargo($plantilla, $solapas, ['lomo_mm' => 3.2] + $propios);
                $libros[] = libroLargo($plantilla, $solapas, ['titulo' => 'Hola'] + $propios);
            }
        }
    }
    foreach (glob(__DIR__ . '/../ejemplos/*.json') ?: [] as $ruta) {
        $libros[] = Libro::desdeArchivo($ruta);
    }

    return $libros;
}

/**
 * Adornos que el texto no debe pisar: líneas, polígonos, rectángulos pequeños (barras y reglas de
 * acento, de 3 mm de alto o menos) y el hueco blanco del código de barras. Los marcos y los fondos
 * son contenedores y no cuentan.
 *
 * @return list<array{texto: string, x0: float, x1: float, y0: float, y1: float}>
 */
function adornos(string $cuerpo): array
{
    $doc = new DOMDocument();
    verdadero($doc->loadXML('<svg xmlns="http://www.w3.org/2000/svg">' . $cuerpo . '</svg>'), 'el dibujo no es XML válido');

    $adornos = [];
    foreach ($doc->getElementsByTagName('line') as $linea) {
        $mitad = (float) $linea->getAttribute('stroke-width') / 2;
        $x = [(float) $linea->getAttribute('x1'), (float) $linea->getAttribute('x2')];
        $y = [(float) $linea->getAttribute('y1'), (float) $linea->getAttribute('y2')];
        $adornos[] = ['texto' => 'una línea', 'x0' => min($x) - $mitad, 'x1' => max($x) + $mitad, 'y0' => min($y) - $mitad, 'y1' => max($y) + $mitad];
    }
    foreach (['polygon', 'polyline'] as $etiqueta) {
        foreach ($doc->getElementsByTagName($etiqueta) as $poligono) {
            $mitad = (float) $poligono->getAttribute('stroke-width') / 2;
            $x = [];
            $y = [];
            foreach (explode(' ', $poligono->getAttribute('points')) as $punto) {
                [$px, $py] = explode(',', $punto);
                $x[] = (float) $px;
                $y[] = (float) $py;
            }
            $adornos[] = ['texto' => 'un ornamento', 'x0' => min($x) - $mitad, 'x1' => max($x) + $mitad, 'y0' => min($y) - $mitad, 'y1' => max($y) + $mitad];
        }
    }
    foreach ($doc->getElementsByTagName('circle') as $circulo) {
        $mitad = (float) $circulo->getAttribute('stroke-width') / 2;
        $cx = (float) $circulo->getAttribute('cx');
        $cy = (float) $circulo->getAttribute('cy');
        $r = (float) $circulo->getAttribute('r') + $mitad;
        $adornos[] = ['texto' => 'un ornamento', 'x0' => $cx - $r, 'x1' => $cx + $r, 'y0' => $cy - $r, 'y1' => $cy + $r];
    }
    foreach ($doc->getElementsByTagName('rect') as $rectangulo) {
        $relleno = $rectangulo->getAttribute('fill');
        $alto = (float) $rectangulo->getAttribute('height');
        $esCodigo = $relleno === '#ffffff' && $alto <= 30.0;
        if ($esCodigo || ($relleno !== 'none' && $alto <= 3.0)) {
            $x = (float) $rectangulo->getAttribute('x');
            $y = (float) $rectangulo->getAttribute('y');
            $adornos[] = [
                'texto' => $esCodigo ? 'el hueco del código de barras' : 'una regla',
                'x0' => $x,
                'x1' => $x + (float) $rectangulo->getAttribute('width'),
                'y0' => $y,
                'y1' => $y + $alto,
            ];
        }
    }

    return $adornos;
}

/**
 * Si dos cajas se montan una sobre otra. Que se toquen por el borde no cuenta.
 *
 * @param array{x0: float, x1: float, y0: float, y1: float} $a
 * @param array{x0: float, x1: float, y0: float, y1: float} $b
 */
function seSolapan(array $a, array $b): bool
{
    return $a['x0'] < $b['x1'] && $b['x0'] < $a['x1'] && $a['y0'] < $b['y1'] && $b['y0'] < $a['y1'];
}

/**
 * Corre el CLI con estos argumentos y devuelve el código de salida, lo impreso y los errores. Un aviso
 * de PHP se vuelve excepción, para que no se cuele sin que lo vea la prueba.
 *
 * @param list<string> $argumentos
 * @return array{int, string, string}
 */
function correrCli(array $argumentos): array
{
    $salida = '';
    $errores = '';
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
            if (!$archivo instanceof SplFileInfo) {
                continue;
            }
            if (in_array(strtolower($archivo->getExtension()), ['html', 'htm', 'js', 'mjs', 'cjs'], true)) {
                $prohibidos[] = $archivo->getPathname();
            }
        }
        igual([], $prohibidos);
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

    'Ningún texto se pisa con otro texto ni con los adornos' => function (): void {
        $pisan = [];
        foreach (librosParaGeometria() as $libro) {
            $plantilla = Plantilla::crear($libro->plantilla);
            $cuerpo = $plantilla->dibujar($libro, new Medidas($libro));
            $cajas = cajasDeTexto($cuerpo, $plantilla);
            $adornos = adornos($cuerpo);
            foreach ($cajas as $indice => $caja) {
                $donde = "{$libro->plantilla}, solapas {$libro->formato->solapas}, título «{$libro->textos->titulo}»";
                foreach (array_slice($cajas, $indice + 1) as $otra) {
                    if (seSolapan($caja, $otra)) {
                        $pisan[] = "$donde: «{$caja['texto']}» con «{$otra['texto']}»";
                    }
                }
                foreach ($adornos as $adorno) {
                    if (seSolapan($caja, $adorno)) {
                        $pisan[] = "$donde: «{$caja['texto']}» con {$adorno['texto']}";
                    }
                }
            }
        }
        verdadero($pisan === [], "textos que se pisan:\n  " . implode("\n  ", array_slice($pisan, 0, 8)));
    },

    'El subtítulo deja bajo el título un espacio proporcional a su tamaño' => function (): void {
        $apretados = [];
        foreach (['clasica', 'sobria'] as $plantilla) {
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

    'Libro pone límites a las medidas y el error nombra el campo' => function (): void {
        $fueraDeLimites = [
            [['formato' => ['ancho_mm' => 5000]], 'ancho_mm'],
            [['formato' => ['alto_mm' => 5000]], 'alto_mm'],
            [['sangrado_mm' => 50], 'sangrado_mm'],
            [['solapas_mm' => 200], 'solapas_mm'], // más anchas que la portada (135 mm)
            [['lomo_mm' => 1000], 'lomo_mm'],
            [['paginas' => 100000], 'paginas'], // un lomo calculado de 5 m
            [['grosor_hoja_mm' => 5], 'grosor_hoja_mm'],
        ];
        foreach ($fueraDeLimites as [$cambios, $campo]) {
            try {
                libroDeEjemplo($cambios);
            } catch (InvalidArgumentException $error) {
                verdadero(str_contains($error->getMessage(), $campo), "el error no nombra '$campo': " . $error->getMessage());
                continue;
            }
            throw new RuntimeException("se aceptó un valor fuera de límites: $campo");
        }

        // Justo en el límite sigue siendo válido.
        libroDeEjemplo(['formato' => ['ancho_mm' => 500, 'alto_mm' => 500], 'sangrado_mm' => 10, 'solapas_mm' => 500, 'lomo_mm' => 150]);
        libroDeEjemplo(['grosor_hoja_mm' => 0.5, 'paginas' => 300]);
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

    'Un JSON que es una lista o un valor suelto no se acepta como libro' => function (): void {
        foreach (['[1, 2, 3]', '"texto"', '42'] as $contenido) {
            $ruta = sys_get_temp_dir() . '/portadas-svg-lista-' . bin2hex(random_bytes(4)) . '.json';
            file_put_contents($ruta, $contenido);
            try {
                Libro::desdeArchivo($ruta);
                throw new RuntimeException("se aceptó $contenido como libro");
            } catch (InvalidArgumentException $error) {
                verdadero(str_contains($error->getMessage(), 'debe ser un objeto'), 'el mensaje no explica que debe ser un objeto: ' . $error->getMessage());
            } finally {
                unlink($ruta);
            }
        }
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
        // El título y la descripción de cada pieza nombran el libro y, en la contratapa, su texto: no cuentan como dibujo.
        $esta = static fn (string $archivo, string $texto): bool => str_contains((string) preg_replace('#<(title|desc)\b[^>]*>.*?</\1>#s', '', (string) file_get_contents("$dir/$archivo")), $texto);

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
        [$codigo, $salida] = correrCli(['--help']);
        igual(0, $codigo);
        verdadero(str_contains($salida, 'Uso:'), '--help no imprime el uso');

        [$codigo, , $errores] = correrCli(['--nada']);
        igual(2, $codigo);
        verdadero(str_contains($errores, '--nada'), 'el error no nombra la opción desconocida');

        igual(2, correrCli(['--salida='])[0]);
        igual(2, correrCli([])[0]);
        igual(2, correrCli(['--comprobar', 'libro.json'])[0]);

        [$codigo, , $errores] = correrCli(['/no/existe.json']);
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

    'La tabla de anchos coincide con las medidas de un navegador' => function (): void {
        // Anchos en em medidos con Chromium (canvas.measureText a 1000 px, con kerning) usando los mismos
        // archivos de fuentes/. Son la referencia independiente de la tabla. La tabla ignora el kerning, que solo
        // acorta, así que nunca debe quedar por debajo de lo medido y se pasa como mucho un 4,5 %.
        $medidos = [
            ['serif', 'normal', 400, 'Apología de Sócrates', 9.806],
            ['serif', 'normal', 400, 'En el año 399 a. C., Sócrates comparece ante un jurado de ciudadanos atenienses acusado de impiedad y de corromper a la juventud.', 62.417],
            ['serif', 'normal', 400, 'Diálogos de Platón sobre la justicia, el alma, el amor y la ciudad ideal', 32.570],
            ['serif', 'normal', 400, 'MARCO TULIO CICERÓN AURELIANO DE LA ACADEMIA', 26.632],
            ['serif', 'normal', 400, 'Edición de ejemplo · Clásicos', 13.761],
            ['serif', 'normal', 400, 'Wagner y Valdés: AVATAR Tipo', 14.532],
            ['serif', 'italic', 400, 'Defensa ante el jurado de Atenas', 14.088],
            ['serif', 'italic', 400, 'Traducción de Patricio de Azcárate', 15.213],
            ['sans', 'normal', 400, 'Tratado militar chino atribuido al general Sun Tzu, que según la tradición lo escribió', 39.367],
            ['sans', 'normal', 400, 'SUN TZU', 4.439],
            ['sans', 'normal', 400, 'Edición de ejemplo · Clásicos', 13.809],
            ['sans', 'normal', 700, 'El arte de la guerra', 9.101],
            ['sans', 'normal', 700, 'Diálogos de Platón sobre la justicia', 16.930],
        ];
        $mal = [];
        foreach ($medidos as [$familia, $estilo, $peso, $texto, $navegador]) {
            $tabla = Anchos::de($familia, $estilo, $peso)->em($texto);
            if ($tabla < $navegador * 0.999 || $tabla > $navegador * 1.045) {
                $mal[] = sprintf('%s/%s/%d «%s»: tabla %.3f, navegador %.3f', $familia, $estilo, $peso, mb_substr($texto, 0, 30), $tabla, $navegador);
            }
        }
        verdadero($mal === [], "la tabla se aleja del navegador:\n  " . implode("\n  ", $mal));
    },

    'Anchos rechaza una cara que no está en la tabla y avisa de las letras que la fuente no tiene' => function (): void {
        lanza(RuntimeException::class, fn () => Anchos::de('serif', 'normal', 700));
        lanza(RuntimeException::class, fn () => Anchos::de('script', 'normal', 400));

        $serif = Anchos::de('serif', 'normal', 400);
        igual('道', $serif->noCubiertas('道Lukasz ñ áé'));
        igual('', $serif->noCubiertas('Apología de Sócrates, «ñandú» — ¿sí?'));
        // Hay latín extendido, griego y cirílico.
        igual('', $serif->noCubiertas('Łukasz Şahin Čapek Ελλάς Война и мир'));
        // El griego politónico solo lo trae Inter, la fuente de la plantilla sobria.
        igual('Ἀῆ', $serif->noCubiertas('Ἀθῆναι'));
        igual('', Anchos::de('sans', 'normal', 400)->noCubiertas('Ἀθῆναι'));
        // Una letra que la fuente no tiene cuenta como la media de las minúsculas, no como cero.
        aproximado(0.551, $serif->em('道'));
    },

    'Cada pieza incrusta solo los trozos de fuente que usa' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        $textos = ['subtitulo' => 'Con cursiva', 'contratapa' => 'Un texto de contratapa.', 'editorial' => 'Editorial de prueba'];
        Generador::escribir(libroDeEjemplo($textos), "$dir/clasica");
        Generador::escribir(libroDeEjemplo(['plantilla' => 'sobria'] + $textos), "$dir/sobria");
        $contenido = static fn (string $ruta): string => (string) file_get_contents($ruta);
        $reglas = static fn (string $ruta, string $estilo): int => substr_count($contenido($ruta), "font-style:$estilo;");

        // Portada: el texto lleva tilde (trozos básico y latino) y el subtítulo va en cursiva, sin tilde (solo el básico).
        igual(2, $reglas("$dir/clasica/portada.svg", 'normal'));
        igual(1, $reglas("$dir/clasica/portada.svg", 'italic'));
        // El lomo no usa cursiva; la contratapa, sin tildes, solo el trozo básico.
        igual(0, $reglas("$dir/clasica/lomo.svg", 'italic'));
        igual(1, $reglas("$dir/clasica/contratapa.svg", 'normal'));
        verdadero(!str_contains($contenido("$dir/sobria/portada.svg"), 'Source Serif'), 'la sobria lleva la serif');
        verdadero(str_contains($contenido("$dir/sobria/portada.svg"), "font-family:'Inter'"), 'la sobria no incrusta Inter');
        verdadero(str_contains($contenido("$dir/sobria/portada.svg"), 'font-weight:700'), 'el título de la sobria va en negrita');

        // Cada trozo lleva su unicode-range y es de verdad un archivo woff2.
        verdadero(preg_match('/base64,([A-Za-z0-9+\/=]+)\) format\(\'woff2\'\);unicode-range:(U\+[0-9A-F,\-U+]+)\}/', $contenido("$dir/clasica/contratapa.svg"), $datos) === 1, 'no hay un trozo completo');
        igual('wOF2', substr((string) base64_decode($datos[1] ?? '', true), 0, 4));
        igual('U+20-7E', $datos[2] ?? '');

        // Los alfabetos solo se incrustan si el texto los usa, y se pagan en peso.
        $abc = libroDeEjemplo(['titulo' => 'Solo ascii', 'autor' => 'Ana']);
        $griego = libroDeEjemplo(['titulo' => 'Solo ascii Ω', 'autor' => 'Ana']);
        $ruso = libroDeEjemplo(['titulo' => 'Война и мир', 'autor' => 'Толстой']);
        foreach (['abc' => $abc, 'griego' => $griego, 'ruso' => $ruso] as $nombre => $libro) {
            Generador::escribir($libro, "$dir/$nombre");
        }
        // (El lomo escribe «título · autor»: el punto medio está en el trozo latino, así que lleva dos.)
        igual(2, $reglas("$dir/abc/lomo.svg", 'normal'));
        igual(3, $reglas("$dir/griego/lomo.svg", 'normal'));
        verdadero(str_contains($contenido("$dir/griego/lomo.svg"), 'U+3A3-'), 'falta el trozo griego');
        verdadero(str_contains($contenido("$dir/ruso/lomo.svg"), 'U+400-'), 'falta el trozo cirílico');
        verdadero(filesize("$dir/clasica/lomo.svg") < 40000, 'el lomo pesa más de 40 KB: ' . filesize("$dir/clasica/lomo.svg"));
        Archivos::borrarDirectorio($dir);
    },

    'Fuentes rechaza una familia o una cara que no tiene' => function (): void {
        lanza(InvalidArgumentException::class, fn () => Fuentes::pila('script'));
        lanza(InvalidArgumentException::class, fn () => Fuentes::estilo(['x' => ['familia' => 'sans', 'estilo' => 'italic', 'peso' => 400, 'texto' => 'a']]));
        lanza(InvalidArgumentException::class, fn () => Fuentes::estilo(['x' => ['familia' => 'serif', 'estilo' => 'normal', 'peso' => 700, 'texto' => 'a']]));
        lanza(InvalidArgumentException::class, fn () => Fuentes::estilo(['x' => ['familia' => 'script', 'estilo' => 'normal', 'peso' => 400, 'texto' => 'a']]));
        igual('', Fuentes::estilo([]));
        // Un texto sin ninguna letra que las fuentes tengan no incrusta nada.
        igual('', Fuentes::estilo(['x' => ['familia' => 'sans', 'estilo' => 'normal', 'peso' => 400, 'texto' => '道']]));
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

    'Nombres compara sin tildes ni mayúsculas y saca nombres de carpeta' => function (): void {
        igual('filosofia y ensayo', Nombres::plegar("  Filosofía   Y\tEnsayo "));
        igual('lukasz', Nombres::plegar('Łukasz'));
        igual('e', Nombres::plegar("e\u{0301}")); // e + tilde suelta, como lo escribe Excel en Mac
        igual('gustavo-adolfo-becquer-rimas-y-leyendas', Nombres::slug('Gustavo Adolfo Bécquer — Rimas y leyendas'));
        igual('', Nombres::slug('道德經'));
        igual('', Nombres::slug(' ¿¡ '));
        // Largo máximo: se corta en un guion, no a media palabra.
        $largo = Nombres::slug(str_repeat('palabra ', 30), 20);
        igual('palabra-palabra', $largo);
        igual('abcdefghij', Nombres::slug('abcdefghijklmnop', 10));
    },

    'El catálogo encuentra una categoría o tradición por slug o por nombre' => function (): void {
        igual('filosofia', Catalogo::categoria('Filosofía y ensayo')['slug']);
        igual('filosofia', Catalogo::categoria('FILOSOFIA')['slug']);
        igual('tipo de texto', Catalogo::categoria('filosofia')['familia']);
        igual('movimiento literario', Catalogo::categoria('Realismo mágico')['familia']);
        igual('arabe', Catalogo::tradicion('Árabe')['slug']);
        igual('medieval europea', Catalogo::tradicion('medieval')['nombre']);
        igual(27, count(Catalogo::categorias()));
        igual(count(Catalogo::tradiciones()), count(array_unique(array_column(Catalogo::tradiciones(), 'slug'))));

        try {
            Catalogo::tradicion('marciana');
            throw new RuntimeException('no lanzó');
        } catch (InvalidArgumentException $error) {
            verdadero(str_contains($error->getMessage(), '«marciana»') && str_contains($error->getMessage(), 'griega, romana'), 'el error no nombra lo que falló ni da opciones: ' . $error->getMessage());
        }
    },

    'Sin época, tradición, categoría ni cita no se arma ficha' => function (): void {
        $libro = libroDeEjemplo();
        igual('', $libro->textos->contratapa);
        igual('', $libro->textos->solapaPortada);
        igual('', $libro->textos->solapaContratapa);
        igual(null, $libro->tradicion);
        igual(null, $libro->categoria);
    },

    'Con tradición y categoría, la contratapa y las solapas se arman con los datos' => function (): void {
        $libro = libroDeEjemplo([
            'titulo' => '¿Qué es la filosofía?', 'subtitulo' => 'Una introducción', 'autor' => 'Autor de prueba', 'traductor' => 'Traductora X',
            'epoca' => 'siglo II', 'tradicion' => 'Romana', 'categoria' => 'Filosofía y ensayo', 'cita' => 'Una frase.', 'paginas' => 224,
        ]);
        igual('romana', $libro->tradicion);
        igual('filosofia', $libro->categoria);
        igual(
            "«Una frase.»\n\n«¿Qué es la filosofía?: Una introducción» es una obra de Autor de prueba (siglo II), de la tradición romana.\n\nTipo de texto: Filosofía y ensayo.\n\nTraducción de Traductora X. 224 páginas.",
            $libro->textos->contratapa,
        );
        igual("Tipo de texto: Filosofía y ensayo.\n\nTraducción de Traductora X. 224 páginas.", $libro->textos->solapaPortada);
        igual("Autor de prueba (siglo II).\n\nTradición romana.", $libro->textos->solapaContratapa);

        // Sin nada más que la tradición, solo lo que se sabe.
        $minimo = libroDeEjemplo(['tradicion' => 'china']);
        igual('«Título de prueba» es una obra de Autor de prueba, de la tradición china.' . "\n\n112 páginas.", $minimo->textos->contratapa);
    },

    'Un texto escrito a mano manda sobre la ficha' => function (): void {
        $libro = libroDeEjemplo(['tradicion' => 'china', 'contratapa' => 'Mi sinopsis.', 'solapa_portada' => 'Mi solapa.']);
        igual('Mi sinopsis.', $libro->textos->contratapa);
        igual('Mi solapa.', $libro->textos->solapaPortada);
        verdadero(str_contains($libro->textos->solapaContratapa, 'Tradición china.'), 'lo que no se escribió sí se arma');
        // Aunque sea vacío: quien escribe "contratapa": "" no quiere texto.
        igual('', libroDeEjemplo(['tradicion' => 'china', 'contratapa' => ''])->textos->contratapa);
    },

    'Una tradición o una categoría que no existe se rechaza nombrando el campo' => function (): void {
        foreach (['tradicion', 'categoria'] as $campo) {
            try {
                libroDeEjemplo([$campo => 'inventada']);
                throw new RuntimeException('no lanzó');
            } catch (InvalidArgumentException $error) {
                verdadero(str_contains($error->getMessage(), "'$campo'") && str_contains($error->getMessage(), 'prueba') && str_contains($error->getMessage(), '«inventada»'), $error->getMessage());
            }
        }
        lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['tradicion' => 5]));
    },

    'Los avisos dicen qué letras no cubren las fuentes' => function (): void {
        igual([], Generador::avisos(libroDeEjemplo(['titulo' => 'Ñandú: ¿cómo? ¡Ásí! «Ü»'])));
        $avisos = Generador::avisos(libroDeEjemplo(['titulo' => 'Lodz 道', 'autor' => 'Ana 德', 'contratapa' => 'Ok']));
        igual(2, count($avisos));
        verdadero(str_contains($avisos[0], 'El título') && str_contains($avisos[0], '道'), $avisos[0]);
        verdadero(str_contains($avisos[1], 'El autor') && str_contains($avisos[1], '德'), $avisos[1]);
    },

    'La lista de ejemplo se lee y cada fila sale con su ficha' => function (): void {
        $libros = Lista::leer(__DIR__ . '/../ejemplos/lista.csv');
        igual(
            ['marco-aurelio-meditaciones', 'lao-tse-tao-te-king', 'anonimo-cantar-de-mio-cid', 'anonimo-las-mil-y-una-noches', 'gustavo-adolfo-becquer-rimas-y-leyendas'],
            array_map(static fn (Libro $libro): string => $libro->id, $libros),
        );
        igual('romana', $libros[0]->tradicion);
        igual('filosofia', $libros[0]->categoria);
        igual('cuentos', $libros[3]->categoria); // escrita por su nombre, con mayúsculas y espacios
        igual('arabe', $libros[3]->tradicion); // escrita con tilde
        verdadero(str_contains($libros[0]->textos->contratapa, '«Lo que no conviene a la colmena, no conviene a la abeja.»'), 'falta la cita');
        verdadero(str_contains($libros[1]->textos->contratapa, 'tradición china'), 'falta la tradición');
        verdadero(!str_contains($libros[1]->textos->contratapa, '«»'), 'cita vacía');
    },

    'La lista acepta ; o tabulador, BOM, encabezados con tilde y celdas con saltos de línea' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $contenido = "\xEF\xBB\xBF" . "Título;Autor;Páginas;Época;Colección;Contratapa;Fondo\r\n"
            . "\"Uno; y dos\";Ana;100;;;\"Primer párrafo.\r\n\r\nSegundo.\";#112233\r\n"
            . "\r\n"
            . ";;;;;;\r\n"
            . "Dos;Beto;50;;;;\r\n";
        file_put_contents("$dir/lista.csv", $contenido);
        $libros = Lista::leer("$dir/lista.csv");
        igual(['ana-uno-y-dos', 'beto-dos'], array_map(static fn (Libro $libro): string => $libro->id, $libros));
        igual('Uno; y dos', $libros[0]->textos->titulo);
        igual("Primer párrafo.\n\nSegundo.", $libros[0]->textos->contratapa);
        igual('#112233', $libros[0]->colores['fondo']);
        aproximado(5.0, $libros[0]->formato->lomo); // 100 páginas: 50 hojas de 0,1 mm
        aproximado(2.5, $libros[1]->formato->lomo); // 50 páginas: 25 hojas
        file_put_contents("$dir/tab.csv", "titulo\tautor\tpaginas\nUno\tAna\t10\n");
        igual('Uno', Lista::leer("$dir/tab.csv")[0]->textos->titulo);
        Archivos::borrarDirectorio($dir);
    },

    'La lista da todos los errores juntos, con el número de fila' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/lista.csv", implode("\n", [
            'titulo,autor,paginas,tradicion,categoria,id',
            'Bien,Ana,100,griega,poesia,',            // fila 2: bien
            'Sin autor,,100,,,',                      // fila 3
            'Mala tradición,Ana,100,marciana,,',      // fila 4
            'Páginas raras,Ana,cien,,,',              // fila 5
            'Mala categoría,Ana,100,,fantasia,',      // fila 6
            'Id con mayúscula,Ana,100,,,Mi Libro',    // fila 7
            'Bien,Ana,100,,,',                        // fila 8: mismo nombre que la fila 2
            'Demasiadas,Ana,100,,,,sobra',            // fila 9
        ]) . "\n");
        try {
            Lista::leer("$dir/lista.csv");
            throw new RuntimeException('no lanzó');
        } catch (InvalidArgumentException $error) {
            $mensaje = $error->getMessage();
            verdadero(str_contains($mensaje, '7 filas con errores'), $mensaje);
            foreach ([
                "fila 3 («Sin autor»): 'titulo' y 'autor' son obligatorios",
                "fila 4 («Mala tradición»): 'tradicion': la tradición «marciana» no existe",
                "fila 5 («Páginas raras»): 'paginas' debe ser un número entero, no «cien»",
                "fila 6 («Mala categoría»): 'categoria': la categoría «fantasia» no existe",
                "fila 7 («Id con mayúscula»): 'id' debe llevar solo letras minúsculas",
                "fila 8 («Bien»): el id 'ana-bien' ya lo usa la fila 2",
                "fila 9 («Demasiadas»): tiene más celdas que columnas",
            ] as $esperado) {
                verdadero(str_contains($mensaje, $esperado), "falta «{$esperado}» en:\n$mensaje");
            }
            verdadero(!str_contains($mensaje, 'fila 2 ('), 'la fila buena no debe salir como error');
        }
        Archivos::borrarDirectorio($dir);
    },

    'La lista rechaza un archivo que no sirve' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $caso = static function (string $contenido, string $esperado) use ($dir): void {
            file_put_contents("$dir/lista.csv", $contenido);
            try {
                Lista::leer("$dir/lista.csv");
                throw new RuntimeException("no lanzó con «{$contenido}»");
            } catch (InvalidArgumentException $error) {
                verdadero(str_contains($error->getMessage(), $esperado), "esperado «{$esperado}» en: " . $error->getMessage());
            }
        };
        $caso("titulo,autor,tradicioon\nA,B,griega\n", 'columnas que no conozco: «tradicioon»');
        $caso("titulo,titulo,autor\nA,B,C\n", "la columna 'titulo' está repetida");
        $caso("titulo,paginas\nA,10\n", "falta la columna 'autor'");
        $caso("titulo,autor,paginas\n", 'no tiene ningún libro');
        $caso('', "falta la columna 'titulo'");
        $caso("titulo,autor\nCaf\xE9,B\n", 'no está en UTF-8');
        lanza(InvalidArgumentException::class, fn () => Lista::leer("$dir/no-existe.csv"));
        Archivos::borrarDirectorio($dir);
    },

    'El CLI genera una lista CSV, avisa de las letras sin cubrir y no escribe nada si hay errores' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/buena.csv", "titulo,autor,paginas,tradicion\nLodz 道,Ana,100,griega\nDos,Beto,50,\n");
        file_put_contents("$dir/mala.csv", "titulo,autor,paginas,tradicion\nUno,Ana,100,griega\nDos,,50,\n");

        [$codigo, $salida, $errores] = correrCli(["--lista=$dir/buena.csv", "--salida=$dir/salida"]);
        igual(0, $codigo);
        igual(['ana-lodz', 'beto-dos'], array_map('basename', glob("$dir/salida/*") ?: []));
        verdadero(is_file("$dir/salida/ana-lodz/portada.svg"), 'falta la portada');
        verdadero(str_contains($errores, 'Aviso, ana-lodz: El título lleva letras que la fuente no tiene (道'), $errores);

        // Una fila mala: ninguna carpeta se escribe, ni la de la fila buena.
        [$codigo, , $errores] = correrCli(["--lista=$dir/mala.csv", "--salida=$dir/otra"]);
        igual(1, $codigo);
        verdadero(str_contains($errores, 'fila 3'), $errores);
        verdadero(!is_dir("$dir/otra"), 'escribió algo con errores en la lista');

        // Un libro de la lista y otro de un JSON con el mismo nombre de carpeta se rechazan.
        file_put_contents("$dir/ana-lodz.json", '{"titulo":"X","autor":"Y","paginas":10}');
        [$codigo, , $errores] = correrCli(["--lista=$dir/buena.csv", "$dir/ana-lodz.json", "--salida=$dir/tercera"]);
        igual(1, $codigo);
        verdadero(str_contains($errores, "se llaman igual ('ana-lodz')"), $errores);
        verdadero(!is_dir("$dir/tercera"), 'escribió algo con un nombre repetido');

        [$codigo, , $errores] = correrCli(['--lista=']);
        igual(2, $codigo);
        [$codigo] = correrCli(['--comprobar', "--lista=$dir/buena.csv"]);
        igual(2, $codigo);
        Archivos::borrarDirectorio($dir);
    },

    'Los adornos caben en la zona que se les da, sea cual sea' => function (): void {
        $fuera = [];
        foreach (array_column(Catalogo::tradiciones(), 'slug') as $tradicion) {
            $adorno = Adorno::de($tradicion);
            foreach ([[36.0, 6.0], [36.0, 4.0], [99.0, 5.0], [17.3, 4.0], [4.0, 5.0]] as [$ancho, $alto]) {
                $zona = new Rectangulo(10.0, 20.0, $ancho, $alto);
                $dibujo = $adorno->friso($zona, '#000000');
                verdadero($dibujo !== '', "$tradicion no dibuja friso");
                foreach (adornos($dibujo) as $caja) {
                    // Un grosor de tolerancia: los trazos son líneas con ancho y no cuentan como relleno.
                    if ($caja['x0'] < $zona->x - 0.5 || $caja['x1'] > $zona->x + $ancho + 0.5 || $caja['y0'] < $zona->y - 0.5 || $caja['y1'] > $zona->y + $alto + 0.5) {
                        $fuera[] = sprintf('friso %s %gx%g: x %.2f–%.2f, y %.2f–%.2f', $tradicion, $ancho, $alto, $caja['x0'], $caja['x1'], $caja['y0'], $caja['y1']);
                        break;
                    }
                }
            }
            foreach ([2.0, 4.4, 9.0] as $radio) {
                foreach (adornos($adorno->emblema(50.0, 60.0, $radio, '#000000')) as $caja) {
                    if ($caja['x0'] < 50.0 - $radio - 0.3 || $caja['x1'] > 50.0 + $radio + 0.3 || $caja['y0'] < 60.0 - $radio - 0.3 || $caja['y1'] > 60.0 + $radio + 0.3) {
                        $fuera[] = sprintf('emblema %s de radio %g: x %.2f–%.2f, y %.2f–%.2f', $tradicion, $radio, $caja['x0'], $caja['x1'], $caja['y0'], $caja['y1']);
                        break;
                    }
                }
            }
        }
        verdadero($fuera === [], "adornos fuera de su zona:\n  " . implode("\n  ", $fuera));
    },

    'Sin adorno propio, la plantilla conserva su rombo y su raya' => function (): void {
        igual(Generico::class, Adorno::de(null)::class);
        igual(Generico::class, Adorno::de('inventada')::class);
        igual('', Adorno::de(null)->friso(new Rectangulo(0.0, 0.0, 36.0, 6.0), '#000000'));
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        Generador::escribir(libroDeEjemplo(), "$dir/sin");
        Generador::escribir(libroDeEjemplo(['tradicion' => 'griega']), "$dir/con");
        $sin = (string) file_get_contents("$dir/sin/contratapa.svg");
        $con = (string) file_get_contents("$dir/con/contratapa.svg");
        verdadero(str_contains($sin, '<line') && !str_contains($sin, '<polyline'), 'sin tradición, la contratapa lleva su raya');
        verdadero(str_contains($con, '<polyline') && !str_contains($con, '<line'), 'con tradición, el friso sustituye a la raya');
        Archivos::borrarDirectorio($dir);
    },

    'La categoría da el color del libro, y los colores propios mandan' => function (): void {
        igual('#ff0000', Color::hsl(0.0, 1.0, 0.5));
        igual('#00ff00', Color::hsl(120.0, 1.0, 0.5));
        igual('#0000ff', Color::hsl(-120.0, 1.0, 0.5));
        igual('#808080', Color::hsl(200.0, 0.0, 0.5));

        $sin = libroDeEjemplo();
        $con = libroDeEjemplo(['categoria' => 'filosofia']);
        $propios = libroDeEjemplo(['categoria' => 'filosofia', 'colores' => ['fondo' => '#112233']]);
        verdadero($sin->colores !== $con->colores, 'la categoría no cambia los colores');
        igual('#112233', $propios->colores['fondo']);
        igual($con->colores['acento'], $propios->colores['acento']);
        igual($con->colores, libroDeEjemplo(['categoria' => 'Filosofía y ensayo'])->colores);
        // Las dos plantillas dan colores distintos para una misma categoría: una clara y una oscura.
        verdadero(Color::esClaro($con->colores['fondo']) && !Color::esClaro(libroDeEjemplo(['categoria' => 'filosofia', 'plantilla' => 'sobria'])->colores['fondo']), 'la clásica es clara y la sobria oscura');
    },

    'Los colores de cada categoría se leen: texto y acento tienen contraste con el fondo' => function (): void {
        $luz = static function (string $hex): float {
            $canal = static fn (int $c): float => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            [$r, $g, $b] = Color::rgb($hex);

            return 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);
        };
        $contraste = static function (string $a, string $b) use ($luz): float {
            [$mas, $menos] = $luz($a) >= $luz($b) ? [$luz($a), $luz($b)] : [$luz($b), $luz($a)];

            return ($mas + 0.05) / ($menos + 0.05);
        };

        $mal = [];
        foreach (Catalogo::categorias() as $categoria) {
            foreach (['clasica', 'sobria'] as $plantilla) {
                $colores = Plantilla::crear($plantilla)->paleta((float) $categoria['tono']);
                $texto = $contraste($colores['texto'], $colores['fondo']);
                $acento = $contraste($colores['acento'], $colores['fondo']);
                if ($texto < 7.0) {
                    $mal[] = sprintf('%s %s: texto %.1f:1', $categoria['slug'], $plantilla, $texto);
                }
                if ($acento < 3.0) {
                    $mal[] = sprintf('%s %s: acento %.1f:1', $categoria['slug'], $plantilla, $acento);
                }
            }
        }
        verdadero($mal === [], "contraste insuficiente:\n  " . implode("\n  ", $mal));
    },

    'Cada categoría tiene su motivo, y cabe en su cuadrado' => function (): void {
        igual(array_column(Catalogo::categorias(), 'slug'), Motivo::CATEGORIAS);
        $mal = [];
        foreach (Motivo::CATEGORIAS as $categoria) {
            foreach ([3.0, 6.5, 12.0] as $radio) {
                $dibujo = Motivo::dibujar($categoria, 50.0, 60.0, $radio, '#000000');
                if ($dibujo === '') {
                    $mal[] = "$categoria no dibuja nada";
                    continue;
                }
                foreach (adornos($dibujo) as $caja) {
                    if ($caja['x0'] < 50.0 - $radio - 0.2 || $caja['x1'] > 50.0 + $radio + 0.2 || $caja['y0'] < 60.0 - $radio - 0.2 || $caja['y1'] > 60.0 + $radio + 0.2) {
                        $mal[] = sprintf('%s radio %g: x %.2f–%.2f, y %.2f–%.2f', $categoria, $radio, $caja['x0'], $caja['x1'], $caja['y0'], $caja['y1']);
                        break;
                    }
                }
            }
        }
        verdadero($mal === [], "motivos fuera de su cuadrado:\n  " . implode("\n  ", $mal));
        igual('', Motivo::dibujar('inventada', 0.0, 0.0, 5.0, '#000000'));
    },

    'La contratapa lleva el motivo de la categoría solo si el libro tiene una' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        Generador::escribir(libroDeEjemplo(), "$dir/sin");
        Generador::escribir(libroDeEjemplo(['categoria' => 'poesia']), "$dir/con");
        $sin = (string) file_get_contents("$dir/sin/contratapa.svg");
        $con = (string) file_get_contents("$dir/con/contratapa.svg");
        verdadero(substr_count($con, '<polyline') > substr_count($sin, '<polyline'), 'la lira no está');
        igual(0, substr_count($sin, '<polyline'));
        Archivos::borrarDirectorio($dir);
    },

    'El lomo lleva el motivo de la categoría solo si es ancho, y la portada sobria también' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        foreach (['ancho' => 224, 'estrecho' => 60] as $nombre => $paginas) {
            Generador::escribir(libroDeEjemplo(['categoria' => 'leyes', 'paginas' => $paginas]), "$dir/$nombre");
        }
        Generador::escribir(libroDeEjemplo(['paginas' => 224]), "$dir/sin");
        Generador::escribir(libroDeEjemplo(['categoria' => 'leyes', 'plantilla' => 'sobria']), "$dir/sobria");
        Generador::escribir(libroDeEjemplo(['plantilla' => 'sobria']), "$dir/sobria-sin");
        $lineas = static fn (string $ruta): int => substr_count((string) file_get_contents($ruta), '<polyline');
        verdadero($lineas("$dir/ancho/lomo.svg") > 0, 'el lomo de 11,2 mm no lleva motivo');
        igual(0, $lineas("$dir/estrecho/lomo.svg")); // 3 mm: no cabe
        igual(0, $lineas("$dir/sin/lomo.svg"));
        verdadero($lineas("$dir/sobria/portada.svg") > $lineas("$dir/sobria-sin/portada.svg"), 'la portada sobria no lleva motivo');
        Archivos::borrarDirectorio($dir);
    },

    'Cada SVG se anuncia como imagen con título, descripción e idioma' => function (): void {
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
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
        Archivos::borrarDirectorio($dir);
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
