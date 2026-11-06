<?php

declare(strict_types=1);

/**
 * Lo que comparten prototipo.php y render.php: escapar HTML, leer JSON, saber qué revisiones aprueban
 * y repartir el texto de una frase entre sus palabras etiquetadas.
 */

const ESTILO_DE_MARCA = [
    'subrayado' => 'solid',
    'ondulado' => 'wavy',
    'punteado' => 'dotted',
    'discontinuo' => 'dashed',
    'ninguna' => 'none',
];

const VEREDICTOS_QUE_APRUEBAN = ['correcto', 'corregido'];
/** Mientras una fila diga esto, el elemento no se da por bueno aunque otro revisor haya puesto «correcto». */
const VEREDICTOS_QUE_BLOQUEAN = ['corregir', 'dudoso'];

/**
 * Lo que un revisor dio por bueno y nadie ha dejado pendiente: aprueba «correcto» o «corregido», y bloquea
 * un «corregir» o un «dudoso» de cualquier revisor hasta que quien mantiene el proyecto lo resuelva.
 *
 * @return array<string, true> "idioma|alcance|elemento"
 */
function aprobadas(string $ruta): array
{
    $fh = fopen($ruta, 'rb');
    if ($fh === false) {
        throw new RuntimeException("No se pudo abrir $ruta");
    }
    fgetcsv($fh, 0, ',', '"', '');
    $ok = $bloqueadas = [];
    while (($f = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        if (count($f) !== 7) {
            continue;
        }
        if (in_array($f[3], VEREDICTOS_QUE_APRUEBAN, true)) {
            $ok["{$f[0]}|{$f[1]}|{$f[2]}"] = true;
        } elseif (in_array($f[3], VEREDICTOS_QUE_BLOQUEAN, true)) {
            $bloqueadas["{$f[0]}|{$f[1]}|{$f[2]}"] = true;
        }
    }
    fclose($fh);
    return array_diff_key($ok, $bloqueadas);
}

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
