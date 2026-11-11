<?php

declare(strict_types=1);

/** Pruebas de la lista CSV y la línea de comandos. Las funciones auxiliares están en ../ayudas.php. */

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
        igual(3, $codigo);
        verdadero(str_contains($errores, '/no/existe.json'), 'el error no nombra el archivo');
        verdadero(!str_contains($errores, 'Failed to open'), 'se coló un aviso de PHP');
    },

    'Dos libros con el mismo nombre de archivo se rechazan sin escribir nada' => function (): void {
        conCarpeta(function (string $dir): void {
            mkdir("$dir/a", 0777, true);
            mkdir("$dir/b", 0777, true);
            file_put_contents("$dir/a/libro.json", '{"titulo":"A","autor":"X","paginas":100}');
            file_put_contents("$dir/b/libro.json", '{"titulo":"B","autor":"Y","paginas":100}');

            $errores = '';
            $codigo = Cli::ejecutar(
                ["$dir/a/libro.json", "$dir/b/libro.json", "--salida=$dir/out"],
                dirname(__DIR__, 2),
                static function (string $texto): void {
                },
                static function (string $texto) use (&$errores): void {
                    $errores .= $texto;
                },
            );

            igual(3, $codigo);
            verdadero(str_contains($errores, "$dir/a/libro.json") && str_contains($errores, "$dir/b/libro.json"), 'el error no nombra los dos archivos');
            verdadero(!is_dir("$dir/out"), 'se escribió algo antes de detectar el repetido');
        });
    },

    'El CLI comprueba los ejemplos y no deja carpetas temporales' => function (): void {
        $antes = count(glob(sys_get_temp_dir() . '/portadas-svg-*') ?: []);
        $codigo = Cli::ejecutar(['--comprobar'], dirname(__DIR__, 2), static function (string $texto): void {
        }, static function (string $texto): void {
        });

        igual(0, $codigo);
        igual($antes, count(glob(sys_get_temp_dir() . '/portadas-svg-*') ?: []));
    },

    'La lista de ejemplo se lee y cada fila sale con su ficha' => function (): void {
        $libros = Lista::leer(__DIR__ . '/../../ejemplos/lista.csv');
        igual(
            ['marco-aurelio-meditaciones', 'lao-tse-tao-te-king', 'anonimo-cantar-de-mio-cid', 'anonimo-las-mil-y-una-noches', 'gustavo-adolfo-becquer-rimas-y-leyendas'],
            array_map(static fn (Libro $libro): string => $libro->id, $libros),
        );
        igual('romana', $libros[0]->tradicion);
        igual('filosofia', $libros[0]->categoria);
        igual('cuentos', $libros[3]->categoria); // escrita por su nombre, con mayúsculas y espacios
        igual('arabe', $libros[3]->tradicion); // escrita con tilde
        igual('Lo que no conviene a la colmena, no conviene a la abeja.', $libros[0]->textos->cita);
        verdadero(str_contains($libros[1]->textos->contratapa, 'tradición china'), 'falta la tradición');
        verdadero(!str_contains($libros[1]->textos->contratapa, '«»'), 'cita vacía');
    },

    'La lista acepta ; o tabulador, BOM, encabezados con tilde y celdas con saltos de línea' => function (): void {
        conCarpeta(function (string $dir): void {
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
        });
    },

    'La lista da todos los errores juntos, con el número de fila' => function (): void {
        conCarpeta(function (string $dir): void {
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
        });
    },

    'La lista rechaza un archivo que no sirve' => function (): void {
        conCarpeta(function (string $dir): void {
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
        });
    },

    'El CLI genera una lista CSV, avisa de las letras sin cubrir y no escribe nada si hay errores' => function (): void {
        conCarpeta(function (string $dir): void {
            file_put_contents("$dir/buena.csv", "titulo,autor,paginas,tradicion\nLodz 道,Ana,100,griega\nDos,Beto,50,\n");
            file_put_contents("$dir/mala.csv", "titulo,autor,paginas,tradicion\nUno,Ana,100,griega\nDos,,50,\n");

            [$codigo, $salida, $errores] = correrCli(["--lista=$dir/buena.csv", "--salida=$dir/salida"]);
            igual(0, $codigo);
            igual(['ana-lodz', 'beto-dos'], array_map('basename', glob("$dir/salida/*") ?: []));
            verdadero(is_file("$dir/salida/ana-lodz/portada.svg"), 'falta la portada');
            verdadero(str_contains($errores, 'Aviso, ana-lodz: El título lleva letras que ni las fuentes ni las fuentes extra tienen (道'), $errores);

            // Una fila mala: ninguna carpeta se escribe, ni la de la fila buena.
            [$codigo, , $errores] = correrCli(["--lista=$dir/mala.csv", "--salida=$dir/otra"]);
            igual(3, $codigo);
            verdadero(str_contains($errores, 'fila 3'), $errores);
            verdadero(!is_dir("$dir/otra"), 'escribió algo con errores en la lista');

            // Un libro de la lista y otro de un JSON con el mismo nombre de carpeta se rechazan.
            file_put_contents("$dir/ana-lodz.json", '{"titulo":"X","autor":"Y","paginas":10}');
            [$codigo, , $errores] = correrCli(["--lista=$dir/buena.csv", "$dir/ana-lodz.json", "--salida=$dir/tercera"]);
            igual(3, $codigo);
            verdadero(str_contains($errores, "se llaman igual ('ana-lodz')"), $errores);
            verdadero(!is_dir("$dir/tercera"), 'escribió algo con un nombre repetido');

            [$codigo, , $errores] = correrCli(['--lista=']);
            igual(2, $codigo);
            [$codigo] = correrCli(['--comprobar', "--lista=$dir/buena.csv"]);
            igual(2, $codigo);
        });
    },

    'La lista admite formato, sangrado, grosor, sentido del lomo, idioma y degradado' => function (): void {
        conCarpeta(function (string $dir): void {
            file_put_contents("$dir/lista.csv", implode("\n", [
                'titulo,autor,paginas,ancho_mm,alto_mm,sangrado_mm,grosor_hoja_mm,lomo_direccion,idioma,degradado,degradado_tipo,degradado_angulo',
                'Uno,Ana,100,"148,5",210,5,0.2,abajo-arriba,en,#112233 #445566,radial,',
                'Dos,Beto,100,,,,,,,auto,,45',
                'Tres,Cleo,100,,,,,,,,,',
            ]) . "\n");
            $libros = Lista::leer("$dir/lista.csv");
            aproximado(148.5, $libros[0]->formato->ancho);
            aproximado(210.0, $libros[0]->formato->alto);
            aproximado(5.0, $libros[0]->formato->sangrado);
            aproximado(10.0, $libros[0]->formato->lomo); // 50 hojas de 0,2 mm
            igual('abajo-arriba', $libros[0]->lomoDireccion);
            igual('en', $libros[0]->idioma);
            igual(['#112233', '#445566'], $libros[0]->degradado?->colores);
            igual('radial', $libros[0]->degradado?->tipo);
            $auto = $libros[1]->degradado;
            if ($auto === null) {
                throw new RuntimeException('la segunda fila no tiene degradado');
            }
            aproximado(45.0, $auto->angulo);
            igual(2, count($auto->colores));
            igual(null, $libros[2]->degradado);
            aproximado(135.0, $libros[2]->formato->ancho);

            foreach ([["degradado_angulo", "girado"], ["degradado_tipo", "cónico"], ["ancho_mm", "ancho"]] as [$columna, $valor]) {
                file_put_contents("$dir/mala.csv", "titulo,autor,paginas,$columna\nUno,Ana,100,$valor\n");
                lanza(InvalidArgumentException::class, fn () => Lista::leer("$dir/mala.csv"));
            }
        });
    },

    'El CLI puede escribir solo lo que cambió, un manifiesto y un JSON, y da el código de salida que toca' => function (): void {
        conCarpeta(function (string $dir): void {
            file_put_contents("$dir/lista.csv", "titulo,autor,paginas\nUno,Ana,100\nDos,Beto,50\n");
            $opciones = ["--lista=$dir/lista.csv", "--salida=$dir/salida", '--incremental', '--manifiesto'];

            [$codigo, $texto] = correrCli($opciones);
            igual(0, $codigo);
            verdadero(str_contains($texto, '2 libros (2 escritos, 0 sin cambios)'), $texto);
            $manifiesto = json_decode((string) file_get_contents("$dir/salida/manifiesto.json"), true);
            verdadero(is_array($manifiesto) && $manifiesto['total'] === 2 && $manifiesto['escritos'] === 2, 'manifiesto.json');
            verdadero(is_array($manifiesto) && isset($manifiesto['libros'][0]['archivos']['portada.svg']) && $manifiesto['libros'][0]['archivos']['portada.svg'] > 1000, 'el manifiesto no trae los tamaños');

            // Segunda vez, sin tocar nada: no se escribe ningún libro.
            $hora = filemtime("$dir/salida/ana-uno/portada.svg");
            [, $texto] = correrCli($opciones);
            verdadero(str_contains($texto, '0 escritos, 2 sin cambios'), $texto);
            igual($hora, filemtime("$dir/salida/ana-uno/portada.svg"));

            // Cambia un libro: solo ese se vuelve a escribir. Y si falta una pieza, también.
            file_put_contents("$dir/lista.csv", "titulo,autor,paginas\nUno,Ana,120\nDos,Beto,50\n");
            [, $texto] = correrCli($opciones);
            verdadero(str_contains($texto, '1 escritos, 1 sin cambios'), $texto);
            unlink("$dir/salida/beto-dos/lomo.svg");
            [, $texto] = correrCli($opciones);
            verdadero(str_contains($texto, '1 escritos, 1 sin cambios') && is_file("$dir/salida/beto-dos/lomo.svg"), $texto);
            // Sin --incremental se escribe todo, y no deja marcas de «sin cambios».
            [, $texto] = correrCli(["--lista=$dir/lista.csv", "--salida=$dir/salida"]);
            verdadero(!str_contains($texto, ': sin cambios'), $texto);

            // --json imprime el manifiesto y nada más por la salida estándar.
            [$codigo, $json] = correrCli(["--lista=$dir/lista.csv", "--salida=$dir/otra", '--json']);
            igual(0, $codigo);
            $datos = json_decode($json, true);
            verdadero(is_array($datos) && $datos['total'] === 2 && !str_contains($json, 'otra/ana-uno:'), $json);

            // Códigos de salida: datos que no valen = 3, uso incorrecto = 2.
            file_put_contents("$dir/mala.csv", "titulo,autor,paginas\nUno,,100\n");
            [$codigo] = correrCli(["--lista=$dir/mala.csv", "--salida=$dir/x"]);
            igual(3, $codigo);
            [$codigo] = correrCli(['--incremental']);
            igual(2, $codigo);
        });
    },

    'Con --omitir-errores la lista salta las filas malas y avisa de cada una' => function (): void {
        conCarpeta(function (string $dir): void {
            file_put_contents("$dir/lista.csv", "titulo,autor,paginas,tradicion\nBuena,Ana,100,griega\nMala,Beto,100,marciana\nOtra buena,Cleo,50,\nSin autor,,50,\n");

            $avisos = [];
            $libros = Lista::leer("$dir/lista.csv", true, static function (string $aviso) use (&$avisos): void {
                $avisos[] = $aviso;
            });
            igual(['ana-buena', 'cleo-otra-buena'], array_map(static fn (Libro $libro): string => $libro->id, $libros));
            igual(2, count($avisos));
            $todos = implode("\n", $avisos);
            verdadero(str_contains($todos, 'se omite la fila 3 («Mala»)') && str_contains($todos, 'se omite la fila 5 («Sin autor»)'), $todos);
            lanza(InvalidArgumentException::class, fn () => Lista::leer("$dir/lista.csv"));

            [$codigo, $salida, $errores] = correrCli(["--lista=$dir/lista.csv", '--omitir-errores', "--salida=$dir/salida"]);
            igual(0, $codigo);
            igual(['ana-buena', 'cleo-otra-buena'], array_map('basename', glob("$dir/salida/*") ?: []));
            verdadero(substr_count($errores, 'Aviso: ') === 2, $errores);
            [$codigo] = correrCli(["--lista=$dir/lista.csv", "--salida=$dir/otra"]);
            igual(3, $codigo);
            verdadero(!is_dir("$dir/otra"), 'escribió algo sin --omitir-errores');
        });
    },

    'Los títulos sin letras latinas no chocan de id, y una fila rota no se queda con el suyo' => function (): void {
        conCarpeta(function (string $dir): void {
            file_put_contents("$dir/lista.csv", "titulo,autor,paginas\n道德經,Lao Tsé,96\n莊子,Lao Tsé,120\n論語,Anónimo,80\n");
            $libros = Lista::leer("$dir/lista.csv", true);
            igual(3, count($libros));
            $ids = array_map(static fn (Libro $libro): string => $libro->id, $libros);
            igual(3, count(array_unique($ids)));
            verdadero(preg_match('/^lao-tse-[0-9a-f]{6}$/', $ids[0]) === 1, $ids[0]);
            verdadero(preg_match('/^anonimo-[0-9a-f]{6}$/', $ids[2]) === 1, $ids[2]);
            // El nombre sale del texto, no de la posición: cambiar el orden no lo cambia.
            file_put_contents("$dir/otra.csv", "titulo,autor,paginas\n莊子,Lao Tsé,120\n道德經,Lao Tsé,96\n");
            $otra = Lista::leer("$dir/otra.csv");
            igual($ids[1], $otra[0]->id);

            // Una fila con un error no reserva su nombre: la buena que viene después no es «duplicada» de ella.
            file_put_contents("$dir/rota.csv", "titulo,autor,paginas,tradicion,id\nMala,Ana,100,marciana,libro\nBuena,Beto,100,,libro\n");
            $avisos = [];
            $buenos = Lista::leer("$dir/rota.csv", true, static function (string $aviso) use (&$avisos): void {
                $avisos[] = $aviso;
            });
            igual(['libro'], array_map(static fn (Libro $libro): string => $libro->id, $buenos));
            igual('Buena', $buenos[0]->textos->titulo);
            igual(1, count($avisos));
        });
    },

    'Numeros lee decimales con punto o coma y rechaza todo lo demás' => function (): void {
        igual(5.6, Numeros::decimal('5,6'));
        igual(5.6, Numeros::decimal('5.6'));
        igual(0.0, Numeros::decimal('0'));
        igual(null, Numeros::decimal('-5')); // sin signo, salvo que se pida
        igual(-45.5, Numeros::decimal('-45,5', true));
        foreach (['', 'abc', '1.000,5', '1e3', ' 5', '5 ', '5,', ',5', '--5'] as $malo) {
            igual(null, Numeros::decimal($malo, true));
        }
    },
];
