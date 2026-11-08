<?php

declare(strict_types=1);

/**
 * Lo que comparten los scripts que leen datos/paleta.json: las 17 categorías UPOS, las matrices de daltonismo, el
 * estilo de cada marca, el CSS de colores y marcas y el reparto de una frase en piezas coloreadas.
 */

require_once __DIR__ . '/comun.php';

/** Las 17 categorías gramaticales de Universal Dependencies (UPOS). */
const UPOS = ['ADJ', 'ADP', 'ADV', 'AUX', 'CCONJ', 'DET', 'INTJ', 'NOUN', 'NUM', 'PART', 'PRON', 'PROPN', 'PUNCT', 'SCONJ', 'SYM', 'VERB', 'X'];

/** De la marca de un grupo (datos/paleta.json) al valor de text-decoration-style. */
const ESTILO_DE_MARCA = [
    'subrayado' => 'solid',
    'ondulado' => 'wavy',
    'punteado' => 'dotted',
    'discontinuo' => 'dashed',
    'ninguna' => 'none',
];

/** Matrices de Machado, Oliveira y Fernandes (2009), severidad 1,0, para simular el daltonismo. */
const DALTONISMO = [
    'protanopia' => ['nombre' => 'Protanopía', 'matriz' => [[0.152286, 1.052583, -0.204868], [0.114503, 0.786281, 0.099216], [-0.003882, -0.048116, 1.051998]]],
    'deuteranopia' => ['nombre' => 'Deuteranopía', 'matriz' => [[0.367322, 0.860646, -0.227968], [0.280085, 0.672501, 0.047413], [-0.011820, 0.042940, 0.968881]]],
    'tritanopia' => ['nombre' => 'Tritanopía', 'matriz' => [[1.255528, -0.076749, -0.178779], [-0.078411, 0.930809, 0.147602], [0.004733, 0.691367, 0.303900]]],
];

/**
 * @param array<string, mixed> $paleta
 * @return array<string, array<string, mixed>> las categorías por código
 */
function categoriasPorCodigo(array $paleta): array
{
    return array_column($paleta['categorias'], null, 'codigo');
}

/**
 * Las variables CSS de color de todas las categorías, una por línea, para un modo («claro» u «oscuro»).
 *
 * @param array<string, mixed> $paleta
 */
function variablesCss(array $paleta, string $modo, string $prefijo, string $sangria): string
{
    $css = '';
    foreach ($paleta['categorias'] as $c) {
        $css .= "{$sangria}--{$prefijo}-{$c['codigo']}: {$c[$modo]};\n";
    }
    return $css;
}

/**
 * Una regla por categoría que enlaza su color con la variable de variablesCss().
 *
 * @param array<string, mixed> $paleta
 */
function reglasDeColor(array $paleta, string $selector, string $prefijo, string $sangria): string
{
    $css = '';
    foreach ($paleta['categorias'] as $c) {
        $css .= "{$sangria}{$selector}[data-c=\"{$c['codigo']}\"] { --color: var(--{$prefijo}-{$c['codigo']}); }\n";
    }
    return $css;
}

/**
 * Una regla por grupo con su marca (el estilo del subrayado).
 *
 * @param array<string, mixed> $paleta
 */
function reglasDeMarca(array $paleta, string $selector, string $sangria): string
{
    $css = '';
    foreach ($paleta['grupos'] as $id => $g) {
        $estilo = ESTILO_DE_MARCA[$g['marca']];
        $valor = $estilo === 'none' ? 'none' : "underline $estilo";
        $css .= "{$sangria}{$selector}[data-g=\"{$id}\"] { --marca: {$valor}; }\n";
    }
    return $css;
}

/**
 * Reparte una frase en piezas: los espacios y las palabras con su categoría.
 *
 * @param array<string, array<string, mixed>> $cats las categorías por código
 * @param list<array{string, string}> $palabras
 * @return list<array{texto: string, cat: string|null, c: array<string, mixed>|null}>
 */
function piezas(array $cats, string $id, string $texto, array $palabras): array
{
    $salida = [];
    foreach (trozos($id, $texto, $palabras) as [$trozo, $cat]) {
        if ($cat !== null && !isset($cats[$cat])) {
            throw new RuntimeException("$id: categoría desconocida $cat.");
        }
        $salida[] = ['texto' => $trozo, 'cat' => $cat, 'c' => $cat === null ? null : $cats[$cat]];
    }
    return $salida;
}
