<?php

declare(strict_types=1);

/** Pruebas de los adornos, los motivos, los colores por categoría y las huellas de los dibujos. Las funciones auxiliares están en ../ayudas.php. */

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
    'Degradado rechaza tipo, ángulo y colores no válidos' => function (): void {
        lanza(InvalidArgumentException::class, fn () => Degradado::desdeArray(['tipo' => 'cónico'], '#ffffff', '#000000'));
        lanza(InvalidArgumentException::class, fn () => Degradado::desdeArray(['angulo' => 'x'], '#ffffff', '#000000'));
        lanza(InvalidArgumentException::class, fn () => Degradado::desdeArray(['colores' => ['#000000']], '#ffffff', '#000000'));
        lanza(InvalidArgumentException::class, fn () => Degradado::desdeArray(['colores' => ['#000000', 'rojo']], '#ffffff', '#000000'));
        lanza(InvalidArgumentException::class, fn () => libroDeEjemplo(['degradado' => ['tipo' => 'cónico']]));
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
        conCarpeta(function (string $dir): void {
            igual(Generico::class, Adorno::de(null)::class);
            igual(Generico::class, Adorno::de('inventada')::class);
            igual('', Adorno::de(null)->friso(new Rectangulo(0.0, 0.0, 36.0, 6.0), '#000000'));
            Generador::escribir(libroDeEjemplo(), "$dir/sin");
            Generador::escribir(libroDeEjemplo(['tradicion' => 'griega']), "$dir/con");
            $sin = (string) file_get_contents("$dir/sin/contratapa.svg");
            $con = (string) file_get_contents("$dir/con/contratapa.svg");
            verdadero(str_contains($sin, '<line') && !str_contains($sin, '<polyline'), 'sin tradición, la contratapa lleva su raya');
            verdadero(str_contains($con, '<polyline') && !str_contains($con, '<line'), 'con tradición, el friso sustituye a la raya');
        });
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
            foreach (['clasica', 'sobria', 'tipografica'] as $plantilla) {
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
        conCarpeta(function (string $dir): void {
            Generador::escribir(libroDeEjemplo(), "$dir/sin");
            Generador::escribir(libroDeEjemplo(['categoria' => 'poesia']), "$dir/con");
            $sin = (string) file_get_contents("$dir/sin/contratapa.svg");
            $con = (string) file_get_contents("$dir/con/contratapa.svg");
            verdadero(substr_count($con, '<polyline') > substr_count($sin, '<polyline'), 'la lira no está');
            igual(0, substr_count($sin, '<polyline'));
        });
    },

    'El lomo lleva el motivo de la categoría solo si es ancho, y la portada sobria también' => function (): void {
        conCarpeta(function (string $dir): void {
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
        });
    },

    'Los dibujos no cambian sin querer' => function (): void {
        $ruta = __DIR__ . '/../dibujos.json';
        $ahora = huellasDeDibujos();
        $leidas = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
        if (!is_array($leidas)) {
            throw new RuntimeException('falta pruebas/dibujos.json: se crea con `php pruebas/ejecutar.php --actualizar-dibujos`');
        }
        /** @var array<string, string> $guardadas */
        $guardadas = $leidas;

        $cambiaron = [];
        foreach ($ahora as $nombre => $huella) {
            if (($guardadas[$nombre] ?? null) !== $huella) {
                $cambiaron[] = $nombre . (isset($guardadas[$nombre]) ? '' : ' (nuevo)');
            }
        }
        foreach (array_diff_key($guardadas, $ahora) as $nombre => $huella) {
            $cambiaron[] = "$nombre (ya no existe)";
        }
        verdadero($cambiaron === [], count($cambiaron) . " dibujos cambiaron; si es a propósito, `php pruebas/ejecutar.php --actualizar-dibujos`:\n  " . implode("\n  ", array_slice($cambiaron, 0, 10)));
    },

    'La contratapa corta lleva el emblema de la tradición como marca de agua, y la larga no' => function (): void {
        conCarpeta(function (string $dir): void {
            Generador::escribir(libroDeEjemplo(['tradicion' => 'griega', 'contratapa' => 'Un texto corto.']), "$dir/corta");
            Generador::escribir(libroDeEjemplo(['tradicion' => 'griega', 'contratapa' => str_repeat('Un texto largo que llega muy abajo. ', 60)]), "$dir/larga");
            Generador::escribir(libroDeEjemplo(['contratapa' => 'Un texto corto.']), "$dir/sin");
            $marca = static fn (string $ruta): bool => str_contains((string) file_get_contents($ruta), '<g opacity="0.3">');
            verdadero($marca("$dir/corta/contratapa.svg"), 'la contratapa corta no lleva marca de agua');
            verdadero(!$marca("$dir/larga/contratapa.svg"), 'la contratapa larga lleva marca de agua y pisaría el texto');
            verdadero(!$marca("$dir/sin/contratapa.svg"), 'sin tradición no hay marca de agua');
        });
    },
];
