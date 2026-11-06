<?php

declare(strict_types=1);

/** Pruebas de los colores y los degradados. Las funciones auxiliares están en ../ayudas.php. */

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

    'Con degradado, cada pieza lo referencia y no lleva HTML ni JS' => function (): void {
        conCarpeta(function (string $dir): void {
            Generador::escribir(libroDeEjemplo(['degradado' => ['angulo' => 90]]), $dir);
            foreach (glob("$dir/*.svg") ?: [] as $archivo) {
                $svg = (string) file_get_contents($archivo);
                verdadero(str_contains($svg, '<linearGradient'), "$archivo sin degradado");
                verdadero(str_contains($svg, 'fill="url(#fondo-degradado)"'), "$archivo no usa el degradado");
                verdadero(!str_contains($svg, '<script') && !str_contains($svg, '<html'), "$archivo lleva HTML o JS");
            }
            verdadero(str_contains((string) file_get_contents("$dir/medidas.txt"), 'Fondo: degradado lineal, 90°'), 'medidas.txt no describe el fondo');
        });
    },
];
