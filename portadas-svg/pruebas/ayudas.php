<?php

declare(strict_types=1);

/** Funciones auxiliares de las pruebas: aserciones, libros de ejemplo, geometría de la maqueta y huellas de los dibujos. */

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

/**
 * Huella de un dibujo: el md5 de su SVG con los números redondeados a dos decimales, para que una diferencia de
 * la última cifra al calcular una curva (senos y cosenos cambian algo entre plataformas) no la altere.
 */
function huella(string $svg): string
{
    return md5((string) preg_replace_callback('/-?\d+\.\d+/', static fn (array $m): string => (string) round((float) $m[0], 2), $svg));
}

/**
 * Una huella de cada dibujo que hacen las plantillas, los adornos y los motivos, con un libro fijo: es la red
 * contra cambios que nadie se propuso. Si cambias a propósito cómo se ve algo, regenera el archivo con
 * `php pruebas/ejecutar.php --actualizar-dibujos` y mira en el diff qué dibujos cambiaron.
 *
 * @return array<string, string>
 */
function huellasDeDibujos(): array
{
    $huellas = [];
    $tradiciones = array_column(Catalogo::tradiciones(), 'slug');
    $categorias = array_column(Catalogo::categorias(), 'slug');
    $zona = new Rectangulo(10.0, 20.0, 60.0, 6.0);
    foreach ($tradiciones as $tradicion) {
        $adorno = Adorno::de($tradicion);
        $huellas["adorno $tradicion friso"] = huella($adorno->friso($zona, '#000000'));
        $huellas["adorno $tradicion emblema"] = huella($adorno->emblema(50.0, 60.0, 4.4, '#000000'));
        $huellas["adorno $tradicion emblema grande"] = huella($adorno->emblema(50.0, 60.0, 34.0, '#000000', 0.3));
    }
    foreach ($categorias as $categoria) {
        $huellas["motivo $categoria"] = huella(Motivo::dibujar($categoria, 50.0, 60.0, 6.5, '#000000'));
    }
    // Cada plantilla con cada tradición, y una categoría distinta por vuelta para pasar por todos los motivos.
    $vuelta = 0;
    foreach (array_keys(Plantilla::disponibles()) as $plantilla) {
        foreach ([null, ...$tradiciones] as $tradicion) {
            $libro = libroDeEjemplo([
                'plantilla' => $plantilla,
                'subtitulo' => 'Un subtítulo de prueba',
                'traductor' => 'Traductor de prueba',
                'editorial' => 'Editorial de prueba',
                'cita' => 'Una cita de prueba.',
                'contratapa' => 'Un texto de contratapa de prueba.',
                'paginas' => 224,
                'categoria' => $categorias[$vuelta++ % count($categorias)],
            ] + ($tradicion === null ? [] : ['tradicion' => $tradicion]));
            $objeto = Plantilla::crear($plantilla);
            foreach (['portada', 'contratapa', 'lomo'] as $vista) {
                $huellas["$plantilla " . ($tradicion ?? 'sin tradición') . " $vista"] = huella($objeto->dibujar($libro, new Medidas($libro), $vista));
            }
        }
    }

    return $huellas;
}

/** Texto de la primera etiqueta de ese nombre en un documento XML, o vacío si no hay. */
function textoDe(DOMDocument $doc, string $etiqueta): string
{
    $nodo = $doc->getElementsByTagName($etiqueta)->item(0);

    return $nodo === null ? '' : $nodo->textContent;
}

/**
 * Corre `$cuerpo` con una carpeta temporal vacía y la borra al acabar, también si la prueba falla (antes una
 * prueba que lanzaba dejaba su carpeta en /tmp).
 *
 * @param callable(string): void $cuerpo
 */
function conCarpeta(callable $cuerpo): void
{
    $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    try {
        $cuerpo($dir);
    } finally {
        Archivos::borrarDirectorio($dir);
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
        'cita' => str_repeat('Una cita larga de la propia obra para forzar su ajuste en la contratapa. ', 6),
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
    foreach (['clasica', 'sobria', 'tipografica'] as $plantilla) {
        foreach ([0.0, 90.0] as $solapas) {
            // Sin tradición y con cada una de las que tienen adorno propio.
            foreach ([null, ...array_column(Catalogo::tradiciones(), 'slug')] as $indice => $tradicion) {
                // Cada libro lleva además una categoría distinta, para que los 27 motivos pasen por la contratapa.
                $categoria = ['categoria' => Catalogo::categorias()[$indice % 27]['slug']];
                $propios = ($tradicion === null ? [] : ['tradicion' => $tradicion]) + $categoria;
                $libros[] = libroLargo($plantilla, $solapas, $propios);
                $libros[] = libroLargo($plantilla, $solapas, ['lomo_mm' => 3.2] + $propios);
                $libros[] = libroLargo($plantilla, $solapas, ['titulo' => 'Hola', 'cita' => 'Una frase corta.'] + $propios);
                $libros[] = libroLargo($plantilla, $solapas, ['cita' => 'Una cita de tamaño mediano que ocupa dos o tres líneas.'] + $propios);
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
