<?php

declare(strict_types=1);

/** Pruebas de las fuentes, el ancho del texto, el texto a trazos y los otros alfabetos. Las funciones auxiliares están en ../ayudas.php. */

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
        conCarpeta(function (string $dir): void {
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
        });
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

    'Los avisos dicen qué letras no cubren las fuentes' => function (): void {
        igual([], Generador::avisos(libroDeEjemplo(['titulo' => 'Ñandú: ¿cómo? ¡Ásí! «Ü»'])));
        $avisos = Generador::avisos(libroDeEjemplo(['titulo' => 'Lodz 道', 'autor' => 'Ana 德', 'contratapa' => 'Ok']));
        igual(2, count($avisos));
        verdadero(str_contains($avisos[0], 'El título') && str_contains($avisos[0], '道'), $avisos[0]);
        verdadero(str_contains($avisos[1], 'El autor') && str_contains($avisos[1], '德'), $avisos[1]);
    },

    'Los contornos de las letras coinciden con la tabla de anchos' => function (): void {
        foreach ([['serif', 'normal', 400], ['serif', 'italic', 400], ['sans', 'normal', 400], ['sans', 'normal', 700]] as [$familia, $estilo, $peso]) {
            $medida = Anchos::de($familia, $estilo, $peso);
            foreach (['A', 'g', 'ñ', 'Ж', 'ω', ' ', '«', '¿'] as $letra) {
                $contorno = Fuentes::contorno($familia, $estilo, $peso, mb_ord($letra));
                if ($contorno === null) {
                    continue; // la serif no tiene todo (sin griego politónico, por ejemplo)
                }
                aproximado($medida->em($letra), $contorno[0] / 1000);
                verdadero(($letra === ' ') === ($contorno[1] === ''), "el contorno de «{$letra}» " . ($contorno[1] === '' ? 'está vacío' : 'no debería estar vacío'));
            }
        }
        verdadero(Fuentes::contorno('serif', 'normal', 400, mb_ord('A')) !== null, 'la serif no tiene la A');
        igual(null, Fuentes::contorno('serif', 'normal', 400, mb_ord('道')));
        lanza(InvalidArgumentException::class, fn () => Fuentes::contorno('serif', 'normal', 700, 65));
    },

    'Con el texto a trazos, la pieza no lleva texto ni fuentes y se ve donde estaría el texto' => function (): void {
        conCarpeta(function (string $dir): void {
            $libro = libroDeEjemplo(['subtitulo' => 'Con cursiva', 'contratapa' => "Un texto de contratapa.\n\nOtro párrafo, con ñ.", 'editorial' => 'Editorial', 'plantilla' => 'clasica', 'titulo' => 'Título Ωmega Ж']);
            Generador::escribir($libro, "$dir/trazos", true);
            Generador::escribir($libro, "$dir/fuente");
            foreach (['portada', 'contratapa', 'lomo'] as $pieza) {
                $doc = new DOMDocument();
                verdadero($doc->load("$dir/trazos/$pieza.svg"), "$pieza no es XML válido");
                igual(0, $doc->getElementsByTagName('text')->length);
                igual(0, $doc->getElementsByTagName('style')->length);
                $ids = [];
                foreach ($doc->getElementsByTagName('path') as $camino) {
                    $ids[$camino->getAttribute('id')] = true;
                }
                foreach ($doc->getElementsByTagName('use') as $uso) {
                    verdadero(isset($ids[ltrim($uso->getAttribute('xlink:href'), '#')]), "$pieza: un <use> apunta a un contorno que no está");
                    igual('', $uso->getAttribute('href')); // Inkscape 1.2 solo lee xlink:href: el href suelto sobra
                }
            }
            $portada = new DOMDocument();
            $portada->load("$dir/trazos/portada.svg");
            verdadero($portada->getElementsByTagName('use')->length > 20, 'la portada no tiene letras');
            verdadero(str_contains((string) file_get_contents("$dir/trazos/lomo.svg"), 'rotate(90)'), 'el lomo no va girado');
            verdadero(filesize("$dir/trazos/lomo.svg") < filesize("$dir/fuente/lomo.svg"), 'el lomo a trazos no pesa menos que con la fuente');

            // El grupo de una línea empieza donde la empezaría el texto: el título centrado queda centrado en la portada.
            $medidas = new Medidas($libro);
            $zona = $medidas->pieza('portada');
            $centro = $zona->x + $zona->ancho / 2;
            $titulo = null; // la línea de letra más grande
            $mayor = 0.0;
            foreach ($portada->getElementsByTagName('g') as $grupo) {
                if (preg_match('/translate\(([\d.]+) ([\d.]+)\) scale\(([\d.]+)\)/', $grupo->getAttribute('transform'), $m) === 1 && (float) $m[3] > $mayor) {
                    $mayor = (float) $m[3];
                    $titulo = (float) $m[1];
                }
            }
            verdadero($titulo !== null, 'no se encontró la línea del título');
            // Centrado: empieza a la izquierda del centro de la portada, y no tan a la izquierda como el margen.
            verdadero($titulo < $centro && $titulo > $zona->x + 10, "el título empieza en x = $titulo y el centro está en $centro");

            [$codigo] = correrCli(['ejemplos/platon-apologia.json', '--trazos', "--salida=$dir/cli"]);
            igual(0, $codigo);
            verdadero(!str_contains((string) file_get_contents("$dir/cli/platon-apologia/contratapa.svg"), '<text'), 'la salida con --trazos lleva <text>');
        });
    },

    'Las letras de una fuente extra se miden y se dibujan con --trazos, y solo con --trazos' => function (): void {
        conCarpeta(function (string $dir): void {
            mkdir("$dir/extra", 0777, true);
            // Una «letra» de mentira: un avance de 1 em y un cuadrado.
            file_put_contents("$dir/extra/chino.json", json_encode(['36947' => [1000, 'M100 0L900 0L900 -800L100 -800Z']]));
            $libro = libroDeEjemplo(['titulo' => 'Tao 道', 'contratapa' => 'Texto.']);
            try {
                // Sin la fuente extra: no hay contorno, y el texto se mide con el ancho medio.
                igual('道', Anchos::de('serif', 'normal', 400)->noCubiertas('道'));
                igual('道', Anchos::de('serif', 'normal', 400)->sinContorno('道'));
                aproximado(0.551, Anchos::de('serif', 'normal', 400)->em('道'));
                igual(null, Fuentes::contorno('serif', 'normal', 400, 36947));

                Fuentes::usarExtras("$dir/extra");
                igual([1000, 'M100 0L900 0L900 -800L100 -800Z'], Fuentes::contorno('serif', 'normal', 400, 36947));
                igual([1000, 'M100 0L900 0L900 -800L100 -800Z'], Fuentes::contorno('sans', 'normal', 700, 36947));
                aproximado(1.0, Anchos::de('serif', 'normal', 400)->em('道')); // ahora mide su ancho real
                igual('道', Anchos::de('serif', 'normal', 400)->noCubiertas('道')); // la fuente incrustada sigue sin tenerla
                igual('', Anchos::de('serif', 'normal', 400)->sinContorno('道'));

                // Con las letras extra, el libro sale a trazos aunque no se pida, y no hay nada que avisar.
                igual([], Generador::avisos($libro));
                igual([], Generador::avisos($libro, true));

                foreach (['trazos' => true, 'sin-pedirlo' => false] as $carpeta => $pedido) {
                    Generador::escribir($libro, "$dir/$carpeta", $pedido);
                    $svg = (string) file_get_contents("$dir/$carpeta/lomo.svg");
                    verdadero(str_contains($svg, 'id="ga36947"') && str_contains($svg, 'M100 0L900 0'), "el lomo ($carpeta) no dibuja la letra extra");
                }
                verdadero(str_contains((string) file_get_contents("$dir/sin-pedirlo/medidas.txt"), 'a trazos'), 'medidas.txt no dice que salió a trazos');
                // Un libro que no usa esas letras sigue saliendo con la fuente.
                Generador::escribir(libroDeEjemplo(), "$dir/normal");
                verdadero(str_contains((string) file_get_contents("$dir/normal/portada.svg"), '@font-face'), 'un libro sin letras extra dejó de llevar fuentes');

                [$codigo] = correrCli(['ejemplos/platon-apologia.json', '--trazos', "--extra=$dir/extra", "--salida=$dir/cli"]);
                igual(0, $codigo);
                [$codigo] = correrCli(['ejemplos/platon-apologia.json', "--extra=$dir/no-existe"]);
                igual(2, $codigo);
            } finally {
                Fuentes::usarExtras(__DIR__ . '/../../fuentes/extra');
            }
            igual(null, Fuentes::contorno('serif', 'normal', 400, 36947)); // y deja todo como estaba
        });
    },

    'Las palabras compuestas de antemano se miden, se encuentran con su puntuación y se ordenan de derecha a izquierda' => function (): void {
        conCarpeta(function (string $dir): void {
            mkdir("$dir/extra", 0777, true);
            $primera = 'M0 0L100 0L100 -100Z';
            $segunda = 'M0 0L200 0L200 -100Z';
            $tercera = 'M0 0L300 0L300 -100Z';
            $cuarta = 'M0 0L400 0L400 -100Z';
            file_put_contents("$dir/extra/rtl.formas.json", json_encode([
                'אב' => [1200, 1, [[0, 0, $primera], [600, 0, $segunda]]],
                'גד' => [1000, 1, [[0, 0, $tercera], [500, 0, $cuarta]]],
            ], JSON_UNESCAPED_UNICODE));
            $id = static fn (string $contorno): string => 'f' . substr(md5($contorno), 0, 8);
            try {
                Fuentes::usarExtras("$dir/extra");
                $medida = Anchos::de('serif', 'normal', 400);

                // El ancho es el de la palabra compuesta, no la suma de sus letras sueltas.
                $espacio = $medida->em(' ');
                aproximado(1.2 + $espacio + 1.0, $medida->em('אב גד'));
                // Los signos pegados se separan para encontrar la palabra.
                igual(['«', 'אב', '».'], Fuentes::trozos('«אב».', Fuentes::formas()));
                igual(['Hola', ' ', 'אב', ' ', 'fin'], Fuentes::trozos('Hola אב fin', Fuentes::formas()));
                aproximado($medida->em('«') + 1.2 + $medida->em('».'), $medida->em('«אב».'));
                igual('', $medida->sinContorno('«אב» גד.'));
                igual('ה', $medida->sinContorno('הה אב')); // una palabra que no está compuesta no se dibuja
                igual([], Generador::avisos(libroDeEjemplo(['titulo' => 'אב גד', 'autor' => 'גד']), true));

                // El lomo escribe «título · autor»: el tramo de derecha a izquierda se invierte dentro de la frase.
                Generador::escribir(libroDeEjemplo(['titulo' => 'אב גד', 'autor' => 'Ana']), "$dir/mixta", true);
                $x = static function (string $svg, string $contorno) use ($id): ?float {
                    return preg_match('/<use xlink:href="#' . $id($contorno) . '"[^>]* x="([\d.]+)"/', $svg, $m) === 1 ? (float) $m[1] : null;
                };
                $lomo = (string) file_get_contents("$dir/mixta/lomo.svg");
                $alef = $x($lomo, $primera);
                $guimel = $x($lomo, $tercera);
                verdadero($alef !== null && $guimel !== null, 'el lomo no dibuja las palabras');
                verdadero($guimel < $alef, "«גד» (a $guimel) debería quedar a la izquierda de «אב» (a $alef): se leen de derecha a izquierda");

                // Una línea escrita solo con palabras de derecha a izquierda se invierte entera; con varias palabras sueltas, no.
                Generador::escribir(libroDeEjemplo(['titulo' => 'Ana', 'autor' => 'אב גד']), "$dir/pura", true);
                $portada = (string) file_get_contents("$dir/pura/portada.svg");
                $alef = $x($portada, $primera);
                $guimel = $x($portada, $tercera);
                verdadero($alef !== null && $guimel !== null && $guimel < $alef, 'el autor, de derecha a izquierda, no se invirtió entero');
            } finally {
                Fuentes::usarExtras(__DIR__ . '/../../fuentes/extra');
            }
            igual([], Fuentes::formas());
        });
    },

    'font-weight acepta palabras de CSS y números, y rechaza lo demás con un mensaje claro' => function (): void {
        $plantilla = Plantilla::crear('sobria');
        igual(Anchos::de('sans', 'normal', 700)->em('Hola'), $plantilla->medidaDe(['font-weight' => 'bolder'])->em('Hola'));
        igual(Anchos::de('sans', 'normal', 400)->em('Hola'), $plantilla->medidaDe(['font-weight' => 'lighter'])->em('Hola'));
        igual(Anchos::de('sans', 'normal', 700)->em('Hola'), $plantilla->medidaDe(['font-weight' => '700'])->em('Hola'));
        igual(Anchos::de('sans', 'normal', 700)->em('Hola'), $plantilla->medidaDe(['font-weight' => 700])->em('Hola'));
        foreach (['semi', '', 0, 1001, -5] as $malo) {
            try {
                $plantilla->medidaDe(['font-weight' => $malo]);
                throw new RuntimeException("aceptó «{$malo}»");
            } catch (InvalidArgumentException $error) {
                verdadero(str_contains($error->getMessage(), 'font-weight no válido'), $error->getMessage());
            }
        }
    },
];
