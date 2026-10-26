<?php

declare(strict_types=1);

/**
 * Lo que comparten prototipo.php y render.php: escapar HTML, leer JSON y repartir el texto de una
 * frase entre sus palabras etiquetadas.
 */

const ESTILO_DE_MARCA = [
    'subrayado' => 'solid',
    'ondulado' => 'wavy',
    'punteado' => 'dotted',
    'discontinuo' => 'dashed',
    'ninguna' => 'none',
];

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return array<string, mixed> */
function leer(string $ruta): array
{
    return json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Reparte el texto de una frase entre sus palabras etiquetadas, conservando los espacios.
 *
 * @param list<array{string, string}> $palabras
 * @return list<array{string, string|null}> trozos: [texto, categoría o null si es un espacio]
 */
function trozos(string $id, string $texto, array $palabras): array
{
    $salida = [];
    $cursor = 0;
    foreach ($palabras as [$forma, $cat]) {
        $pos = strpos($texto, $forma, $cursor);
        if ($pos === false) {
            throw new RuntimeException("$id: la palabra «{$forma}» no aparece en el texto después de la posición $cursor.");
        }
        $entre = substr($texto, $cursor, $pos - $cursor);
        if (trim($entre) !== '') {
            throw new RuntimeException("$id: hay texto sin etiquetar entre palabras: «{$entre}».");
        }
        if ($entre !== '') {
            $salida[] = [$entre, null];
        }
        $salida[] = [$forma, $cat];
        $cursor = $pos + strlen($forma);
    }
    if (trim(substr($texto, $cursor)) !== '') {
        throw new RuntimeException("$id: queda texto sin etiquetar al final: «" . substr($texto, $cursor) . '».');
    }
    return $salida;
}
