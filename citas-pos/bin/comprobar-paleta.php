<?php

declare(strict_types=1);

/**
 * Comprueba datos/paleta.json: las 17 categorías UPOS, el contraste de cada color contra su
 * fondo y qué pares de colores se confunden (a simple vista y con daltonismo).
 *
 *   php citas-pos/bin/comprobar-paleta.php          # informe; falla si hay errores
 *   php citas-pos/bin/comprobar-paleta.php --lista  # además, la lista de pares confundibles
 *
 * Criterios (son una aproximación, no una norma):
 *  - Contraste: WCAG 2.x, mínimo 4,5:1 contra el fondo, en modo claro y en modo oscuro.
 *  - Distancia entre colores: ΔE de CIE76 en CIELAB (la fórmula simple). Con visión normal
 *    todos los pares deben pasar de UMBRAL_NORMAL.
 *  - Daltonismo: se simula con las matrices de Machado, Oliveira y Fernandes (2009), al 100 %
 *    de severidad, en RGB lineal. Un par de grupos distintos que se confunda no es un error,
 *    porque cada grupo lleva además su propia marca (el estilo del subrayado). Un par del
 *    mismo grupo que se confunda es un aviso: se distingue por la etiqueta y no por el color.
 */

const UMBRAL_CONTRASTE = 4.5;
const UMBRAL_NORMAL = 18.0;
const UMBRAL_DALTONISMO = 10.0;

const UPOS = ['ADJ', 'ADP', 'ADV', 'AUX', 'CCONJ', 'DET', 'INTJ', 'NOUN', 'NUM', 'PART', 'PRON', 'PROPN', 'PUNCT', 'SCONJ', 'SYM', 'VERB', 'X'];

const DALTONISMO = [
    'protanopía' => [[0.152286, 1.052583, -0.204868], [0.114503, 0.786281, 0.099216], [-0.003882, -0.048116, 1.051998]],
    'deuteranopía' => [[0.367322, 0.860646, -0.227968], [0.280085, 0.672501, 0.047413], [-0.011820, 0.042940, 0.968881]],
    'tritanopía' => [[1.255528, -0.076749, -0.178779], [-0.078411, 0.930809, 0.147602], [0.004733, 0.691367, 0.303900]],
];

/** @return array{float, float, float} RGB lineal */
function rgbLineal(string $hex): array
{
    $v = array_map(
        static function (string $c): float {
            $s = hexdec($c) / 255;
            return $s <= 0.04045 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        },
        str_split(ltrim($hex, '#'), 2)
    );
    return [$v[0], $v[1], $v[2]];
}

/** @param array{float, float, float} $c */
function luminancia(array $c): float
{
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function contraste(string $a, string $b): float
{
    $la = luminancia(rgbLineal($a));
    $lb = luminancia(rgbLineal($b));
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * @param array{float, float, float} $c RGB lineal
 * @return array{float, float, float} L*, a*, b* (D65)
 */
function lab(array $c): array
{
    $x = 0.4124564 * $c[0] + 0.3575761 * $c[1] + 0.1804375 * $c[2];
    $y = 0.2126729 * $c[0] + 0.7151522 * $c[1] + 0.0721750 * $c[2];
    $z = 0.0193339 * $c[0] + 0.1191920 * $c[1] + 0.9503041 * $c[2];
    $f = static fn (float $t): float => $t > 216 / 24389 ? $t ** (1 / 3) : ((24389 / 27) * $t + 16) / 116;
    $fx = $f($x / 0.95047);
    $fy = $f($y);
    $fz = $f($z / 1.08883);
    return [116 * $fy - 16, 500 * ($fx - $fy), 200 * ($fy - $fz)];
}

/**
 * @param array{float, float, float} $c RGB lineal
 * @param array<int, array<int, float>> $m
 * @return array{float, float, float}
 */
function simular(array $c, array $m): array
{
    $r = [];
    foreach ($m as $fila) {
        $r[] = min(1.0, max(0.0, $fila[0] * $c[0] + $fila[1] * $c[1] + $fila[2] * $c[2]));
    }
    return [$r[0], $r[1], $r[2]];
}

/**
 * @param array{float, float, float} $a
 * @param array{float, float, float} $b
 */
function deltaE(array $a, array $b): float
{
    return sqrt(($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2);
}

function main(array $argv): int
{
    $ruta = __DIR__ . '/../datos/paleta.json';
    $paleta = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
    $errores = [];
    $avisos = [];
    $lista = in_array('--lista', $argv, true);

    $cats = [];
    foreach ($paleta['categorias'] as $c) {
        $cats[$c['codigo']] = $c;
    }
    $faltan = array_diff(UPOS, array_keys($cats));
    $sobran = array_diff(array_keys($cats), UPOS);
    if ($faltan !== [] || $sobran !== [] || count($paleta['categorias']) !== count(UPOS)) {
        $errores[] = 'Las categorías no son las 17 de UPOS (faltan: ' . implode(' ', $faltan) . '; sobran: ' . implode(' ', $sobran) . ').';
    }
    $marcas = [];
    foreach ($paleta['grupos'] as $id => $g) {
        if (isset($marcas[$g['marca']])) {
            $errores[] = "Los grupos {$marcas[$g['marca']]} y $id comparten la marca {$g['marca']}: no se distinguirían sin color.";
        }
        $marcas[$g['marca']] = $id;
    }
    foreach ($cats as $codigo => $c) {
        if (!isset($paleta['grupos'][$c['grupo']])) {
            $errores[] = "$codigo: grupo desconocido {$c['grupo']}.";
        }
        foreach (['claro', 'oscuro'] as $modo) {
            if (!preg_match('/^#[0-9a-f]{6}$/', $c[$modo] ?? '')) {
                $errores[] = "$codigo: el color $modo no es #rrggbb en minúsculas.";
            }
        }
    }
    if ($errores !== []) {
        return informe($errores, $avisos);
    }

    foreach (['claro', 'oscuro'] as $modo) {
        $fondo = $paleta['fondo'][$modo];
        $peor = ['', 99.0];
        foreach ($cats as $codigo => $c) {
            $r = contraste($c[$modo], $fondo);
            if ($r < $peor[1]) {
                $peor = [$codigo, $r];
            }
            if ($r < UMBRAL_CONTRASTE) {
                $errores[] = sprintf('%s en modo %s: contraste %.2f:1 contra %s (mínimo %.1f).', $codigo, $modo, $r, $fondo, UMBRAL_CONTRASTE);
            }
        }
        printf("Modo %-6s contraste mínimo %.2f:1 (%s)\n", $modo, $peor[1], $peor[0]);

        $vistas = ['visión normal' => null] + DALTONISMO;
        foreach ($vistas as $nombre => $matriz) {
            $confundidos = [];
            $minimo = [999.0, '', ''];
            $codigos = array_keys($cats);
            foreach ($codigos as $i => $a) {
                foreach (array_slice($codigos, $i + 1) as $b) {
                    $ca = rgbLineal($cats[$a][$modo]);
                    $cb = rgbLineal($cats[$b][$modo]);
                    if ($matriz !== null) {
                        $ca = simular($ca, $matriz);
                        $cb = simular($cb, $matriz);
                    }
                    $d = deltaE(lab($ca), lab($cb));
                    if ($d < $minimo[0]) {
                        $minimo = [$d, $a, $b];
                    }
                    $mismoGrupo = $cats[$a]['grupo'] === $cats[$b]['grupo'];
                    if ($matriz === null && $d < UMBRAL_NORMAL) {
                        $errores[] = sprintf('%s y %s en modo %s se parecen demasiado a simple vista (ΔE %.1f, mínimo %.0f).', $a, $b, $modo, $d, UMBRAL_NORMAL);
                    } elseif ($matriz !== null && $d < UMBRAL_DALTONISMO) {
                        $confundidos[] = [$a, $b, $d, $mismoGrupo];
                        if ($mismoGrupo) {
                            $avisos[] = sprintf('%s y %s (mismo grupo) se confunden con %s en modo %s (ΔE %.1f): se distinguen por la etiqueta, no por el color.', $a, $b, $nombre, $modo, $d);
                        }
                    }
                }
            }
            $entreGrupos = count(array_filter($confundidos, static fn (array $p): bool => !$p[3]));
            printf(
                "  %-14s ΔE mínimo %5.1f (%s/%s); confundibles: %d de grupos distintos (los salva la marca), %d del mismo grupo\n",
                $nombre,
                $minimo[0],
                $minimo[1],
                $minimo[2],
                $entreGrupos,
                count($confundidos) - $entreGrupos
            );
            if ($lista && $confundidos !== []) {
                foreach ($confundidos as [$a, $b, $d, $mg]) {
                    printf("      %s ~ %s  ΔE %.1f%s\n", $a, $b, $d, $mg ? '  (mismo grupo)' : '');
                }
            }
        }
    }
    return informe($errores, $avisos);
}

/**
 * @param list<string> $errores
 * @param list<string> $avisos
 */
function informe(array $errores, array $avisos): int
{
    foreach (array_unique($avisos) as $a) {
        echo "AVISO  $a\n";
    }
    foreach ($errores as $e) {
        echo "ERROR  $e\n";
    }
    echo $errores === [] ? "Paleta correcta.\n" : count($errores) . " error(es).\n";
    return $errores === [] ? 0 : 1;
}

exit(main($argv));
