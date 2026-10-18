<?php

declare(strict_types=1);

/**
 * Pruebas de portadas-svg, sin dependencias. Uso: php pruebas/ejecutar.php
 */

require __DIR__ . '/../src/autocarga.php';

use PortadasSvg\Generador;
use PortadasSvg\Libro;
use PortadasSvg\Medidas;
use PortadasSvg\Plantilla;
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
        verdadero(in_array('sobrelomo.svg', $con, true), 'falta sobrelomo.svg con solapas');
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
