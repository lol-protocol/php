<?php

declare(strict_types=1);

use PortadasSvg\Cli;
use PortadasSvg\Generador;
use PortadasSvg\PiezasSegunInterruptores;

/**
 * Piezas ocultas por interruptor (interruptores/, función pos.portadas). Sin --interruptores, nada cambia.
 * Los interruptores de estas pruebas están copiados a una carpeta temporal, con su propio estado: no dependen
 * de lo que tenga el repositorio en su var/.
 */

/** Copia el código y el catálogo de interruptores a $destino, con el estado que se pida. */
$interruptoresTemporales = function (string $destino, array $estado): string {
    $origen = dirname(__DIR__, 3) . '/interruptores';
    mkdir("$destino/src", 0775, true);
    mkdir("$destino/var", 0775, true);
    copy("$origen/catalogo.json", "$destino/catalogo.json");
    foreach (['Interruptores.php', 'Excepciones.php', 'RegistroJsonl.php'] as $archivo) {
        copy("$origen/src/$archivo", "$destino/src/$archivo");
    }
    file_put_contents("$destino/var/estado.json", json_encode($estado, JSON_PRETTY_PRINT));
    return $destino;
};

/** Rutas de todos los archivos con ese nombre dentro de $dir (a cualquier profundidad). */
$buscarArchivo = function (string $dir, string $nombre): array {
    $encontrados = [];
    if (!is_dir($dir)) {
        return $encontrados;
    }
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterador as $archivo) {
        if ($archivo->getFilename() === $nombre) {
            $encontrados[] = $archivo->getPathname();
        }
    }
    return $encontrados;
};

return [
    'Generador: sin piezas ocultas, la huella y la salida son las de siempre' => function (): void {
        conCarpeta(function (string $dir): void {
            $libro = libroDeEjemplo(['solapas_mm' => 90]);
            igual(Generador::huella($libro, false), Generador::huella($libro, false, []));
            igual(['portada.svg', 'contratapa.svg', 'lomo.svg', 'sobrelomo.svg', 'medidas.txt'], Generador::escribir($libro, "$dir/sin"));
            verdadero(Generador::sinCambios($libro, "$dir/sin", false), 'recién escrita, la carpeta está al día');
        });
    },

    'Generador: una pieza oculta no se escribe y las demás sí' => function (): void {
        conCarpeta(function (string $dir): void {
            $archivos = Generador::escribir(libroDeEjemplo(), "$dir/oculta", false, ['lomo.svg']);
            igual(['portada.svg', 'contratapa.svg', 'cubierta-completa.svg', 'medidas.txt'], $archivos);
            verdadero(!is_file("$dir/oculta/lomo.svg"), 'lomo.svg no debe existir');
        });
    },

    'Generador: ocultar o mostrar una pieza cambia la huella y obliga a reescribir' => function (): void {
        conCarpeta(function (string $dir): void {
            $libro = libroDeEjemplo();
            Generador::escribir($libro, "$dir/x");
            verdadero(Generador::sinCambios($libro, "$dir/x", false), 'recién escrita, al día');
            verdadero(!Generador::sinCambios($libro, "$dir/x", false, ['lomo.svg']), 'con el lomo oculto ya no está al día');

            Generador::escribir($libro, "$dir/x", false, ['lomo.svg']);
            verdadero(Generador::sinCambios($libro, "$dir/x", false, ['lomo.svg']), 'escrita con el lomo oculto, al día');
            verdadero(!is_file("$dir/x/lomo.svg"), 'el lomo que había de antes se quitó');
            verdadero(!Generador::sinCambios($libro, "$dir/x", false), 'sin ocultar, vuelve a no estar al día');
        });
    },

    'PiezasSegunInterruptores: el estado de fábrica no oculta nada' => function () use ($interruptoresTemporales): void {
        conCarpeta(function (string $dir) use ($interruptoresTemporales): void {
            igual([], PiezasSegunInterruptores::ocultas($interruptoresTemporales("$dir/i", [])));
        });
    },

    'PiezasSegunInterruptores: una opción apagada oculta solo su pieza' => function () use ($interruptoresTemporales): void {
        conCarpeta(function (string $dir) use ($interruptoresTemporales): void {
            $carpeta = $interruptoresTemporales("$dir/i", ['pos.portadas:lomo' => false]);
            igual(['lomo.svg'], PiezasSegunInterruptores::ocultas($carpeta));
        });
    },

    'PiezasSegunInterruptores: apagar la función oculta todas las piezas' => function () use ($interruptoresTemporales): void {
        conCarpeta(function (string $dir) use ($interruptoresTemporales): void {
            $carpeta = $interruptoresTemporales("$dir/i", ['pos.portadas' => false]);
            igual(['portada.svg', 'contratapa.svg', 'lomo.svg', 'cubierta-completa.svg', 'sobrelomo.svg'], PiezasSegunInterruptores::ocultas($carpeta));
        });
    },

    'PiezasSegunInterruptores: la cubierta completa y el sobrelomo son la misma opción' => function () use ($interruptoresTemporales): void {
        conCarpeta(function (string $dir) use ($interruptoresTemporales): void {
            $carpeta = $interruptoresTemporales("$dir/i", ['pos.portadas:cubierta-completa' => false]);
            igual(['cubierta-completa.svg', 'sobrelomo.svg'], PiezasSegunInterruptores::ocultas($carpeta));
        });
    },

    'PiezasSegunInterruptores: sin código de interruptores, error claro' => function (): void {
        conCarpeta(function (string $dir): void {
            lanza(RuntimeException::class, fn () => PiezasSegunInterruptores::ocultas("$dir/no-existe"));
        });
    },

    'Cli: --interruptores no se combina con --comprobar' => function (): void {
        $errores = '';
        $codigo = Cli::ejecutar(['--comprobar', '--interruptores=x'], dirname(__DIR__, 2),
            static function (string $texto): void {
            },
            static function (string $texto) use (&$errores): void {
                $errores .= $texto;
            });
        igual(2, $codigo);
        verdadero(str_contains($errores, '--comprobar no admite --interruptores'), 'dice qué opción sobra');
    },

    'Cli: un interruptor que no se puede leer corta antes de escribir nada' => function (): void {
        conCarpeta(function (string $dir): void {
            $salida = "$dir/salida";
            $codigo = Cli::ejecutar([dirname(__DIR__, 2) . '/ejemplos/cervantes-quijote.json', "--interruptores=$dir/no-existe", "--salida=$salida"],
                dirname(__DIR__, 2),
                static function (string $texto): void {
                },
                static function (string $texto): void {
                });
            igual(2, $codigo);
            verdadero(!is_dir($salida), 'no se creó la carpeta de salida');
        });
    },

    'Cli: con --interruptores, la pieza apagada no sale y el resto sí' => function () use ($interruptoresTemporales, $buscarArchivo): void {
        conCarpeta(function (string $dir) use ($interruptoresTemporales, $buscarArchivo): void {
            $carpeta = $interruptoresTemporales("$dir/i", ['pos.portadas:lomo' => false]);
            $codigo = Cli::ejecutar([dirname(__DIR__, 2) . '/ejemplos/cervantes-quijote.json', "--interruptores=$carpeta", "--salida=$dir/out"],
                dirname(__DIR__, 2),
                static function (string $texto): void {
                },
                static function (string $texto): void {
                });
            igual(0, $codigo);
            igual([], $buscarArchivo("$dir/out", 'lomo.svg'));
            verdadero(count($buscarArchivo("$dir/out", 'portada.svg')) === 1, 'la portada sí se escribió');
        });
    },
];
