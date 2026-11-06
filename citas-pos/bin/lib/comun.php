<?php

declare(strict_types=1);

/**
 * Lo que comparten los scripts de bin/: leer JSON, las fechas y el «hoy» de las comprobaciones, el dominio de
 * una fuente, los códigos de idioma y de escritura, escapar HTML, leer el registro de revisiones y repartir el
 * texto de una frase entre sus palabras etiquetadas.
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

/** La carpeta datos/ del proyecto, junto a bin/: también en la copia temporal de las pruebas. */
const DATOS = __DIR__ . '/../../datos';

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return array<string, mixed> */
function leerJson(string $ruta): array
{
    return json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
}

function textoNoVacio(mixed $v): bool
{
    return is_string($v) && trim($v) !== '';
}

// --- fechas -----------------------------------------------------------------------------------------------------

/**
 * «Hoy», para las fechas y para el año de corte de los derechos. La variable CITAS_POS_HOY (AAAA-MM-DD) lo fija:
 * las pruebas la usan para no depender del reloj.
 */
function hoy(): DateTimeImmutable
{
    $fijada = getenv('CITAS_POS_HOY');
    if ($fijada === false || $fijada === '') {
        return new DateTimeImmutable('today');
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fijada);
    if ($d === false || $d->format('Y-m-d') !== $fijada) {
        throw new RuntimeException('CITAS_POS_HOY debe ser AAAA-MM-DD, y es «' . $fijada . '».');
    }
    return $d;
}

function fechaValida(mixed $f): bool
{
    if (!is_string($f)) {
        return false;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $f);
    return $d !== false && $d->format('Y-m-d') === $f;
}

/** Una fecha AAAA-MM-DD válida que no es posterior a hoy. */
function fechaNoFutura(mixed $f): bool
{
    return fechaValida($f) && $f <= hoy()->format('Y-m-d');
}

/**
 * El último año de fallecimiento que da una obra libre aun con vida+100, el plazo más largo de copyright-laws/: la
 * obra de quien murió en el año A es libre desde el 1 de enero de A+101. En 2026, 1925. Avanza un año cada 1 de enero.
 */
function ultimoAnioDeFallecimiento(): int
{
    return (int) hoy()->format('Y') - 101;
}

// --- fuentes, idiomas y escrituras --------------------------------------------------------------------------------

/**
 * El dominio principal de una dirección, para saber si dos fuentes son del mismo sitio: sin mayúsculas, sin «www.»
 * ni subdominios (es.wikisource.org y en.wikisource.org son wikisource.org), y con tres niveles bajo los sufijos de
 * dos niveles más comunes (example.co.uk, example.com.mx).
 */
function dominioPrincipal(string $url): string
{
    $host = rtrim(mb_strtolower((string) parse_url($url, PHP_URL_HOST)), '.');
    $partes = explode('.', $host);
    if (count($partes) <= 2) {
        return $host;
    }
    $ultima = $partes[count($partes) - 1];
    $penultima = $partes[count($partes) - 2];
    $dosNiveles = strlen($ultima) === 2 && in_array($penultima, ['co', 'com', 'org', 'net', 'gov', 'edu', 'ac', 'or', 'ne', 'go'], true);
    return implode('.', array_slice($partes, $dosNiveles ? -3 : -2));
}

/**
 * Un valor para lang=: el idioma de dos o tres letras y, si hace falta, la escritura (es, zh-Hans). Se ancla con \z y
 * no con $, que en PHP también casa antes de un salto de línea final.
 */
function langValido(string $lang): bool
{
    return preg_match('/^[a-z]{2,3}(-[A-Z][a-z]{3})?\z/', $lang) === 1;
}

/** Una escritura ISO 15924: cuatro letras, la primera en mayúscula (Hans, Latn). */
function escrituraValida(mixed $e): bool
{
    return is_string($e) && preg_match('/^[A-Z][a-z]{3}\z/', $e) === 1;
}

// --- revisiones -------------------------------------------------------------------------------------------------

/**
 * Lee revisiones.csv: la cabecera y cada fila con su número de línea (la cabecera es la 1; las vacías se saltan).
 *
 * @return array{cabecera: list<string|null>, filas: list<array{int, list<string|null>}>}
 */
function leerRevisiones(string $ruta): array
{
    $fh = fopen($ruta, 'rb');
    if ($fh === false) {
        throw new RuntimeException("No se pudo abrir $ruta");
    }
    $cabecera = fgetcsv($fh, 0, ',', '"', '') ?: [];
    $filas = [];
    $n = 1;
    while (($f = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        $n++;
        if ($f !== [null]) {
            $filas[] = [$n, $f];
        }
    }
    fclose($fh);
    return ['cabecera' => $cabecera, 'filas' => $filas];
}

/**
 * Lo que un revisor dio por bueno y nadie ha dejado pendiente: aprueba «correcto» o «corregido», y bloquea
 * un «corregir» o un «dudoso» de cualquier revisor hasta que quien mantiene el proyecto lo resuelva.
 *
 * @return array<string, true> "idioma|alcance|elemento"
 */
function aprobadas(string $ruta): array
{
    $ok = $bloqueadas = [];
    foreach (leerRevisiones($ruta)['filas'] as [, $f]) {
        if (count($f) !== 7) {
            continue;
        }
        if (in_array($f[3], VEREDICTOS_QUE_APRUEBAN, true)) {
            $ok["{$f[0]}|{$f[1]}|{$f[2]}"] = true;
        } elseif (in_array($f[3], VEREDICTOS_QUE_BLOQUEAN, true)) {
            $bloqueadas["{$f[0]}|{$f[1]}|{$f[2]}"] = true;
        }
    }
    return array_diff_key($ok, $bloqueadas);
}

// --- texto etiquetado -------------------------------------------------------------------------------------------

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
