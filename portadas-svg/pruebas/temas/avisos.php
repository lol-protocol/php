<?php

declare(strict_types=1);

/** Pruebas de los avisos, el idioma de la ficha y los datos que se validan (ISBN, largos, contraste). Las funciones auxiliares están en ../ayudas.php. */

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
use PortadasSvg\Frases;
use PortadasSvg\Fuentes;
use PortadasSvg\Generador;
use PortadasSvg\Isbn;
use PortadasSvg\Libro;
use PortadasSvg\Lista;
use PortadasSvg\Mayusculas;
use PortadasSvg\Medidas;
use PortadasSvg\Nombres;
use PortadasSvg\Numeros;
use PortadasSvg\Plantilla;
use PortadasSvg\Rectangulo;
use PortadasSvg\Svg;
use PortadasSvg\Texto;

return [
    'Un espacio de no ruptura une lo que tiene a los lados' => function (): void {
        $medida = new FactorFijo(1.0);
        // Con un espacio normal, «siglo II» se parte; con uno de no ruptura, no.
        igual(['Obra del siglo', 'II a. C. y más'], Texto::envolver('Obra del siglo II a. C. y más', 14.0, 1.0, $medida));
        igual(['Obra del', "siglo\u{00A0}II a. C.", 'y más'], Texto::envolver("Obra del siglo\u{00A0}II a. C. y más", 14.0, 1.0, $medida));
    },

    'Frases escribe la ficha en el idioma del libro y vuelve al español si no lo tiene' => function (): void {
        $datos = ['titulo' => 'Meditations', 'autor' => 'Marcus Aurelius', 'paginas' => 200, 'tradicion' => 'romana', 'categoria' => 'filosofia', 'traductor' => 'T'];
        $ficha = static fn (string $idioma): string => str_replace("\u{00A0}", ' ', Libro::desdeArray('x', ['idioma' => $idioma] + $datos)->textos->contratapa);
        verdadero(str_contains($ficha('en'), 'is a work by Marcus Aurelius, in the Roman tradition.'), $ficha('en'));
        verdadero(str_contains($ficha('en'), 'Text type: Philosophy and essay.') && str_contains($ficha('en'), 'Translated by T. 200 pages.'), $ficha('en'));
        verdadero(str_contains($ficha('pt-BR'), 'é uma obra de') && str_contains($ficha('fr'), 'est une œuvre de'), 'pt o fr');
        igual($ficha('es'), $ficha('de')); // sin frases en alemán: español
        igual(['es', 'en', 'pt', 'fr'], Frases::idiomas());
        // Cada idioma traduce las 16 tradiciones y las 27 categorías.
        foreach (Frases::idiomas() as $idioma) {
            foreach (Catalogo::tradiciones() as $tradicion) {
                verdadero(Frases::nombre($idioma, 'tradiciones', $tradicion['slug'], '') !== '' || $idioma === 'es', "$idioma no traduce la tradición {$tradicion['slug']}");
            }
            foreach (Catalogo::categorias() as $categoria) {
                verdadero(Frases::nombre($idioma, 'categorias', $categoria['slug'], '') !== '' || $idioma === 'es', "$idioma no traduce la categoría {$categoria['slug']}");
            }
        }
        // «Traducción de…» de la portada también sale en su idioma.
        $dir = sys_get_temp_dir() . '/portadas-svg-prueba-' . bin2hex(random_bytes(4));
        conCarpeta(function (string $dir): void {
            Generador::escribir(libroDeEjemplo(['idioma' => 'en', 'traductor' => 'Ana']), "$dir/en");
            verdadero(str_contains((string) file_get_contents("$dir/en/portada.svg"), 'Translated by Ana'), 'la portada no dice «Translated by»');
        });
    },

    'Nombres convierte el griego y el cirílico en letras latinas para los nombres de carpeta' => function (): void {
        igual('omega-athinai-voina-i-mir', Nombres::slug('Ωmega Ἀθῆναι Война и мир'));
        igual('platon-apologia-sokratous', Nombres::slug('Πλάτων Ἀπολογία Σωκράτους'));
        igual('', Nombres::slug('道德經'));
    },

    'Isbn comprueba el dígito de control' => function (): void {
        foreach (['978-84-376-0494-7', '9788437604947', 'ISBN 978-84-376-0494-7', '0-306-40615-2', '080442957X'] as $bueno) {
            verdadero(Isbn::valido($bueno), "$bueno debería ser válido");
        }
        foreach (['978-84-376-0494-8', '123', '', '0-306-40615-3', '9788437604947X', 'abcdefghijklm'] as $malo) {
            verdadero(!Isbn::valido($malo), "$malo no debería ser válido");
        }
    },

    'Mayusculas respeta el turco' => function (): void {
        igual('ISTANBUL', Mayusculas::de('istanbul'));
        igual('İSTANBUL', Mayusculas::de('istanbul', 'tr'));
        igual('İSTANBUL', Mayusculas::de('istanbul', 'tr-TR'));
        igual('DIYARBAKIR', Mayusculas::de('diyarbakır', 'es'));
    },

    'Libro rechaza un texto que se pasa de largo y nombra el campo' => function (): void {
        foreach (['titulo' => 301, 'autor' => 201, 'cita' => 1001, 'contratapa' => 5001] as $campo => $largo) {
            try {
                libroDeEjemplo([$campo => str_repeat('a', $largo)]);
                throw new RuntimeException("aceptó $largo caracteres en $campo");
            } catch (InvalidArgumentException $error) {
                verdadero(str_contains($error->getMessage(), "'$campo'") && str_contains($error->getMessage(), (string) $largo), $error->getMessage());
            }
        }
        igual(300, mb_strlen(libroDeEjemplo(['titulo' => str_repeat('a', 300)])->textos->titulo));
    },

    'Color mide el contraste' => function (): void {
        aproximado(21.0, round(Color::contraste('#000000', '#ffffff'), 6));
        aproximado(1.0, Color::contraste('#777777', '#777777'));
        verdadero(Color::contraste('#767676', '#ffffff') >= 4.5, 'el gris #767676 sobre blanco es el mínimo de 4,5:1');
        verdadero(Color::contraste('#808080', '#ffffff') < 4.5, '#808080 sobre blanco no llega a 4,5:1');
    },

    'Los avisos dicen del ISBN, el lomo estrecho, el contraste y los textos recortados' => function (): void {
        $todos = static fn (Libro $libro): string => implode("\n", Generador::avisos($libro));
        igual('', $todos(libroDeEjemplo(['isbn' => '978-84-376-0494-7'])));
        verdadero(str_contains($todos(libroDeEjemplo(['isbn' => '978-84-376-0494-8'])), 'no es válido'), 'ISBN malo sin aviso');
        verdadero(str_contains($todos(libroDeEjemplo(['paginas' => 20])), 'El lomo mide 1 mm'), $todos(libroDeEjemplo(['paginas' => 20])));
        igual('', $todos(libroDeEjemplo(['paginas' => 100])));
        verdadero(str_contains($todos(libroDeEjemplo(['colores' => ['fondo' => '#777777', 'texto' => '#808080']])), 'casi no se lee'), 'contraste malo sin aviso');
        verdadero(str_contains($todos(libroDeEjemplo(['degradado' => ['colores' => ['#ffffff', '#222222']], 'colores' => ['fondo' => '#ffffff', 'texto' => '#000000']])), 'casi no se lee'), 'el final del degradado no se mira');
        // Una contratapa que no cabe ni al tamaño mínimo se recorta con «…» y debe avisar.
        $largo = libroDeEjemplo(['contratapa' => str_repeat("Una frase larga que llena la contratapa hasta que no cabe. ", 80)]);
        verdadero(str_contains($todos($largo), 'La contratapa recorta un texto'), $todos($largo));
        igual('', $todos(libroDeEjemplo(['contratapa' => 'Corta.'])));
    },
];
