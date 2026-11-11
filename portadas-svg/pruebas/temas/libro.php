<?php

declare(strict_types=1);

/** Pruebas de el libro, sus medidas, la ficha, el catálogo y la salida de las piezas. Las funciones auxiliares están en ../ayudas.php. */

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
        conCarpeta(function (string $dir): void {

            $sin = Generador::escribir(libroDeEjemplo(), "$dir/sin");
            igual(['portada.svg', 'contratapa.svg', 'lomo.svg', 'cubierta-completa.svg', 'medidas.txt'], $sin);

            $con = Generador::escribir(libroDeEjemplo(['solapas_mm' => 90]), "$dir/con");
            igual(['portada.svg', 'contratapa.svg', 'lomo.svg', 'sobrelomo.svg', 'medidas.txt'], $con);
            foreach (glob("$dir/con/*.svg") ?: [] as $archivo) {
                $xml = new DOMDocument();
                verdadero($xml->loadXML((string) file_get_contents($archivo)), "$archivo no es XML válido");
            }

        });
    },

    'El proyecto no tiene HTML ni JS' => function (): void {
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2), FilesystemIterator::SKIP_DOTS));
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
        conCarpeta(function (string $dir): void {
            Generador::escribir(libroDeEjemplo(['solapas_mm' => 90]), $dir);
            $sinSolapas = Generador::escribir(libroDeEjemplo(), $dir);

            igual(['portada.svg', 'contratapa.svg', 'lomo.svg', 'cubierta-completa.svg', 'medidas.txt'], $sinSolapas);
            verdadero(!is_file("$dir/sobrelomo.svg"), 'sigue en la carpeta el sobrelomo de la escritura anterior');
            verdadero(!str_contains((string) file_get_contents("$dir/medidas.txt"), 'sobrelomo.svg'), 'medidas.txt sigue listando el sobrelomo');
        });
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

    'Los paneles de cada vista' => function (): void {
        $con = new Medidas(libroDeEjemplo(['solapas_mm' => 90]));
        igual(['contratapa', 'lomo', 'portada'], $con->paneles('cubierta'));
        igual(['solapa-contratapa', 'contratapa', 'lomo', 'portada', 'solapa-portada'], $con->paneles('sobrelomo'));
        igual(['contratapa', 'lomo', 'portada'], (new Medidas(libroDeEjemplo()))->paneles('sobrelomo'));
        lanza(InvalidArgumentException::class, fn () => $con->paneles('inexistente'));
    },

    'Cada pieza lleva solo su parte del dibujo' => function (): void {
        conCarpeta(function (string $dir): void {
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
        });
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
        igual('Una frase.', $libro->textos->cita); // la cita no entra en el texto de la ficha: la contratapa la compone aparte
        igual(
            "«¿Qué es la filosofía?: Una introducción» es una obra de Autor de prueba (siglo II), de la tradición romana.\n\nTipo de texto: Filosofía y ensayo.\n\nTraducción de Traductora X. 224\u{00A0}páginas.",
            $libro->textos->contratapa,
        );
        igual("Tipo de texto: Filosofía y ensayo.\n\nTraducción de Traductora X. 224\u{00A0}páginas.", $libro->textos->solapaPortada);
        igual("Autor de prueba (siglo II).\n\nTradición romana.", $libro->textos->solapaContratapa);

        // Sin nada más que la tradición, solo lo que se sabe.
        $minimo = libroDeEjemplo(['tradicion' => 'china']);
        igual('«Título de prueba» es una obra de Autor de prueba, de la tradición china.' . "\n\n112\u{00A0}páginas.", $minimo->textos->contratapa);
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

    'Un libro con traductor avisa de los derechos de la traducción' => function (): void {
        igual([], Generador::avisos(libroDeEjemplo()));
        $avisos = Generador::avisos(libroDeEjemplo(['traductor' => 'Patricio de Azcárate']));
        igual(1, count($avisos));
        verdadero(str_contains($avisos[0], 'Patricio de Azcárate') && str_contains($avisos[0], 'dominio público'), $avisos[0]);
    },

    'Las categorías copiadas siguen siendo las de Marcalibros' => function (): void {
        // Si esa carpeta cambia sus categorías, este archivo hay que actualizarlo a mano (ver README).
        $ruta = __DIR__ . '/../../../marcalibros-plataforma-pos-brand-identity/catalog/manifest.json';
        if (!is_file($ruta)) {
            return; // el proyecto se usa suelto, sin el resto del repositorio
        }
        $manifiesto = json_decode((string) file_get_contents($ruta), true);
        $categorias = is_array($manifiesto) ? ($manifiesto['categorias'] ?? null) : null;
        if (!is_array($categorias)) {
            throw new RuntimeException('el manifiesto de Marcalibros no tiene categorías');
        }
        $origen = [];
        foreach ($categorias as $categoria) {
            $origen[] = is_array($categoria) ? [$categoria['slug'] ?? null, $categoria['nombre'] ?? null, $categoria['familia'] ?? null] : null;
        }
        $copia = array_map(static fn (array $categoria): array => [$categoria['slug'], $categoria['nombre'], $categoria['familia']], Catalogo::categorias());
        igual($origen, $copia);
    },

    'Los libros de ejemplo se cargan' => function (): void {
        $ejemplos = glob(__DIR__ . '/../../ejemplos/*.json') ?: [];
        verdadero(count($ejemplos) >= 2, 'faltan ejemplos');
        foreach ($ejemplos as $ruta) {
            Libro::desdeArchivo($ruta);
        }
    },
];
