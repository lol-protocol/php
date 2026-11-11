<?php

declare(strict_types=1);

/** Pruebas del diseño: formatos con nombre, tema, volumen, imagen, logo y las plantillas ilustrada y cartel. Las funciones auxiliares están en ../ayudas.php. */

use PortadasSvg\Color;
use PortadasSvg\Generador;
use PortadasSvg\Libro;
use PortadasSvg\Lista;
use PortadasSvg\Plantilla;

/** Un PNG de 1×1, para probar la incrustación sin depender de ningún archivo del repositorio. */
function pngDePrueba(string $ruta): string
{
    file_put_contents($ruta, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true));

    return $ruta;
}

return [
    'Un formato con nombre da las medidas, y uno que no existe da error' => function (): void {
        $libro = libroDeEjemplo(['formato' => 'Bolsillo']);
        aproximado(110.0, $libro->formato->ancho);
        aproximado(178.0, $libro->formato->alto);
        foreach (Libro::FORMATOS as $nombre => [$ancho, $alto]) {
            aproximado($ancho, libroDeEjemplo(['formato' => $nombre])->formato->ancho);
            aproximado($alto, libroDeEjemplo(['formato' => $nombre])->formato->alto);
        }
        lanza(InvalidArgumentException::class, static fn () => libroDeEjemplo(['formato' => 'gigante']));
        // El formato como objeto con medidas sigue valiendo.
        aproximado(120.0, libroDeEjemplo(['formato' => ['ancho_mm' => 120, 'alto_mm' => 190]])->formato->ancho);
    },

    'El tema pone la paleta clara u oscura, y uno desconocido da error' => function (): void {
        $claro = libroDeEjemplo(['tema' => 'claro', 'plantilla' => 'sobria', 'categoria' => 'filosofia']);
        $oscuro = libroDeEjemplo(['tema' => 'oscuro', 'plantilla' => 'clasica', 'categoria' => 'filosofia']);
        verdadero(Color::esClaro($claro->colores['fondo']), 'el tema claro tiene fondo claro, aunque la plantilla sea oscura');
        verdadero(!Color::esClaro($oscuro->colores['fondo']), 'el tema oscuro tiene fondo oscuro, aunque la plantilla sea clara');
        foreach ([$claro, $oscuro] as $libro) {
            verdadero(Color::contraste($libro->colores['fondo'], $libro->colores['texto']) >= 7.0, 'el texto se lee bien en el tema');
            verdadero(Color::contraste($libro->colores['fondo'], $libro->colores['acento']) >= 3.0, 'el acento se ve en el tema');
        }
        // Sin categoría, el tema también vale.
        verdadero(!Color::esClaro(libroDeEjemplo(['tema' => 'oscuro'])->colores['fondo']), 'el tema oscuro sin categoría');
        lanza(InvalidArgumentException::class, static fn () => libroDeEjemplo(['tema' => 'sepia']));
    },

    'El volumen va en el lomo y en el pie, en el idioma del libro' => function (): void {
        conCarpeta(static function (string $dir): void {
            Generador::escribir(libroDeEjemplo(['volumen' => '3', 'coleccion' => 'Clásicos', 'editorial' => 'Ed']), "$dir/es");
            Generador::escribir(libroDeEjemplo(['volumen' => '3', 'idioma' => 'fr']), "$dir/fr");
            Generador::escribir(libroDeEjemplo(['coleccion' => 'Clásicos']), "$dir/sin");
            $leer = static fn (string $ruta): string => (string) file_get_contents($ruta);
            verdadero(str_contains($leer("$dir/es/lomo.svg"), 'Vol. 3'), 'el lomo lleva el volumen');
            verdadero(str_contains($leer("$dir/es/contratapa.svg"), 'Clásicos · Vol. 3'), 'el pie lleva colección y volumen');
            verdadero(str_contains($leer("$dir/fr/lomo.svg"), 'T. 3'), 'en francés es «T. 3»');
            verdadero(!str_contains($leer("$dir/sin/lomo.svg"), 'Vol.'), 'sin volumen no se escribe nada');
        });
    },

    'La imagen se incrusta en la portada, el logo en la contratapa, y un archivo malo da error' => function (): void {
        conCarpeta(static function (string $dir): void {
            $png = pngDePrueba("$dir/foto.png");
            file_put_contents("$dir/malo.svg", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
            file_put_contents("$dir/enlace.svg", '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="http://example.com/x.png"/></svg>');
            file_put_contents("$dir/bien.svg", '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
            file_put_contents("$dir/texto.txt", 'no es una imagen');

            $libro = libroDeEjemplo(['plantilla' => 'ilustrada', 'imagen' => $png, 'logo' => "$dir/bien.svg"]);
            Generador::escribir($libro, "$dir/salida");
            $portada = (string) file_get_contents("$dir/salida/portada.svg");
            $contratapa = (string) file_get_contents("$dir/salida/contratapa.svg");
            verdadero(str_contains($portada, 'xlink:href="data:image/png;base64,'), 'la portada lleva la imagen incrustada');
            verdadero(str_contains($contratapa, 'data:image/svg+xml;base64,'), 'la contratapa lleva el logo');
            verdadero(!str_contains($portada, 'href="http'), 'ningún enlace a archivos de fuera');
            igual([realpath($png), realpath("$dir/bien.svg")], $libro->archivosExternos());

            foreach (['malo.svg', 'enlace.svg', 'texto.txt', 'no-existe.png'] as $archivo) {
                lanza(InvalidArgumentException::class, static fn () => libroDeEjemplo(['imagen' => "$dir/$archivo"]));
            }
            file_put_contents("$dir/grande.png", str_repeat('x', 2_100_000));
            lanza(InvalidArgumentException::class, static fn () => libroDeEjemplo(['imagen' => "$dir/grande.png"]));
        });
    },

    'Las rutas de imagen de un JSON o de una lista se leen desde la carpeta del archivo' => function (): void {
        conCarpeta(static function (string $dir): void {
            pngDePrueba("$dir/foto.png");
            file_put_contents("$dir/libro.json", json_encode(['titulo' => 'T', 'autor' => 'A', 'paginas' => 100, 'imagen' => 'foto.png']));
            igual(realpath("$dir/foto.png"), Libro::desdeArchivo("$dir/libro.json")->imagen);
            file_put_contents("$dir/lista.csv", "id,titulo,autor,paginas,imagen,formato,tema,volumen\nuno,T,A,100,foto.png,a5,oscuro,2\n");
            $libros = Lista::leer("$dir/lista.csv", false, static function (string $mensaje): void {
            });
            igual(realpath("$dir/foto.png"), $libros[0]->imagen);
            aproximado(148.0, $libros[0]->formato->ancho);
            igual('2', $libros[0]->textos->volumen);
        });
    },

    'Las plantillas ilustrada y cartel están y dibujan sin imagen ni adorno' => function (): void {
        igual(true, isset(Plantilla::disponibles()['ilustrada'], Plantilla::disponibles()['cartel']));
        conCarpeta(static function (string $dir): void {
            foreach (['ilustrada', 'cartel'] as $plantilla) {
                $archivos = Generador::escribir(libroDeEjemplo(['plantilla' => $plantilla, 'subtitulo' => 'Un subtítulo', 'traductor' => 'Alguien']), "$dir/$plantilla");
                verdadero(in_array('portada.svg', $archivos, true), "$plantilla escribe la portada");
            }
        });
    },

    'Cambiar la imagen de un libro cambia su huella, y una plantilla sin ilustración avisa' => function (): void {
        conCarpeta(static function (string $dir): void {
            $png = pngDePrueba("$dir/foto.png");
            $libro = libroDeEjemplo(['plantilla' => 'ilustrada', 'imagen' => $png]);
            $antes = Generador::huella($libro, false);
            file_put_contents($png, (string) file_get_contents($png) . 'cambio');
            verdadero($antes !== Generador::huella($libro, false), 'la huella cambia con la imagen');
            igual([], array_filter(Generador::avisos($libro), static fn (string $a): bool => str_contains($a, 'la imagen no se usa')));
            $otro = libroDeEjemplo(['plantilla' => 'clasica', 'imagen' => $png]);
            igual(1, count(array_filter(Generador::avisos($otro), static fn (string $a): bool => str_contains($a, 'la imagen no se usa'))));
        });
    },
];
