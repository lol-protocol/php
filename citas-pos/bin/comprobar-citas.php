<?php

declare(strict_types=1);

/**
 * Valida datos/citas.json: que cada cita tenga la ficha completa de CRITERIOS.md y que ninguna
 * figure como «verificada» sin texto exacto, edición y dos fuentes independientes.
 *
 *   php citas-pos/bin/comprobar-citas.php            # valida citas-pos/datos/citas.json
 *   php citas-pos/bin/comprobar-citas.php otro.json  # valida otro archivo (para probar)
 *
 * Las fechas y el año de corte de los derechos salen de «hoy»: CITAS_POS_HOY=AAAA-MM-DD lo fija.
 *
 * No comprueba que el texto coincida con la fuente (eso es trabajo de una persona, y queda
 * anotado en la ficha): comprueba que la ficha diga lo que hace falta para poder comprobarlo.
 */

require_once __DIR__ . '/lib/paleta.php';

const ESTADOS = ['sin-verificar', 'verificada', 'descartada'];
const CAMPOS = ['id', 'idioma', 'texto', 'autor', 'fallecimiento', 'obra', 'ubicacion', 'edicion', 'fuentes', 'dominio_publico', 'tema', 'etiquetas', 'estado'];
const PALABRAS_MIN = 6;
const PALABRAS_MAX = 20;
const SIN_PALABRA = ['PUNCT', 'SYM'];

function plural(int $n, string $uno, string $varios): string
{
    return $n . ' ' . ($n === 1 ? $uno : $varios);
}

/** Las palabras de un texto con espacios: los trozos que llevan alguna letra o cifra (un «—» suelto no es una palabra). */
function palabrasConEspacios(string $texto): int
{
    $trozos = preg_split('/\s+/u', $texto) ?: [];
    return count(array_filter($trozos, static fn (string $t): bool => preg_match('/[\p{L}\p{N}]/u', $t) === 1));
}

/**
 * Cuántas palabras tiene el texto de la cita, o null si en ese idioma no se puede contar.
 *
 * En los idiomas con espacios son los trozos separados por espacios que llevan letras o cifras. En los que no
 * separan las palabras (datos/idiomas.json: sin_espacios) un texto entero sería «una palabra»: se cuentan las
 * etiquetas que no son puntuación (la segmentación que ya hizo quien etiquetó) y, si aún no hay etiquetas, las
 * palabras que separa intl; sin ninguna de las dos, null.
 *
 * @param array<string, mixed> $c
 * @param array<string, mixed> $idioma
 */
function contarPalabras(array $c, array $idioma): ?int
{
    $texto = trim((string) $c['texto']);
    if (empty($idioma['sin_espacios'])) {
        return palabrasConEspacios($texto);
    }
    $etiquetas = $c['etiquetas'] ?? null;
    if (is_array($etiquetas) && $etiquetas !== []) {
        return count(array_filter($etiquetas, static fn (mixed $par): bool => is_array($par) && is_string($par[1] ?? null) && !in_array($par[1], SIN_PALABRA, true)));
    }
    if (class_exists(IntlBreakIterator::class)) {
        $cortador = IntlBreakIterator::createWordInstance($idioma['codigo2']);
        $cortador->setText($texto);
        $n = 0;
        while ($cortador->next() !== IntlBreakIterator::DONE) {
            if ($cortador->getRuleStatus() >= IntlBreakIterator::WORD_NONE_LIMIT) {
                $n++;
            }
        }
        return $n;
    }
    return null;
}

/**
 * @param array<string, mixed> $c
 * @param array<string, mixed> $idioma el registro del idioma de datos/idiomas.json
 * @return list<string>
 */
function errores(array $c, string $donde, array $idioma): array
{
    $e = [];
    $estado = $c['estado'];

    if (!textoNoVacio($c['autor'] ?? null) || !textoNoVacio($c['obra'] ?? null) || !textoNoVacio($c['ubicacion'] ?? null)) {
        $e[] = "$donde: autor, obra y ubicacion son obligatorios.";
    }
    $limite = ultimoAnioDeFallecimiento();
    if (!is_int($c['fallecimiento'] ?? null)) {
        $e[] = "$donde: fallecimiento debe ser un año (entero).";
    } elseif ($c['fallecimiento'] > $limite && $estado !== 'descartada') {
        $e[] = "$donde: el autor murió en {$c['fallecimiento']}; solo entran fallecidos hasta $limite (o la cita queda «descartada»).";
    }
    if (array_key_exists('escritura', $c) && $c['escritura'] !== null && !escrituraValida($c['escritura'])) {
        $e[] = "$donde: escritura debe ser un código ISO 15924 de cuatro letras (por ejemplo Hans).";
    }
    if (!textoNoVacio($c['dominio_publico'] ?? null)) {
        $e[] = "$donde: dominio_publico debe explicar cómo se comprobó.";
    }
    if (!is_array($c['tema'] ?? null) || $c['tema'] === [] || array_filter($c['tema'], static fn (mixed $t): bool => !textoNoVacio($t)) !== []) {
        $e[] = "$donde: tema debe ser una lista de textos, no vacía.";
    }
    if (!is_array($c['fuentes'] ?? null)) {
        $e[] = "$donde: fuentes debe ser una lista.";
        return $e;
    }
    if ($c['texto'] === null && $c['etiquetas'] !== null) {
        $e[] = "$donde: no puede tener etiquetas sin texto.";
    }

    if ($estado === 'sin-verificar') {
        $p = $c['por_comprobar'] ?? null;
        if (!is_array($p) || $p === [] || array_filter($p, static fn (mixed $t): bool => !textoNoVacio($t)) !== []) {
            $e[] = "$donde: una cita sin verificar debe decir qué falta comprobar (por_comprobar, lista no vacía).";
        }
    }

    if ($estado === 'descartada' && !textoNoVacio($c['motivo_descarte'] ?? null)) {
        $e[] = "$donde: una cita descartada necesita motivo_descarte.";
    }

    if ($estado === 'verificada') {
        if (!textoNoVacio($c['texto'])) {
            $e[] = "$donde: una cita verificada necesita su texto exacto.";
        } else {
            $n = contarPalabras($c, $idioma);
            if ($n === null) {
                $e[] = "$donde: en {$idioma['codigo']} las palabras no van separadas por espacios; para contarlas hacen falta las etiquetas (o la extensión intl de PHP).";
            } elseif ($n < PALABRAS_MIN || $n > PALABRAS_MAX) {
                $e[] = "$donde: el texto tiene " . plural($n, 'palabra', 'palabras') . '; debe tener entre ' . PALABRAS_MIN . ' y ' . PALABRAS_MAX . '.';
            }
        }
        if (!textoNoVacio($c['edicion'])) {
            $e[] = "$donde: una cita verificada necesita la edición de la que sale.";
        }
        $dominios = [];
        foreach ($c['fuentes'] as $i => $f) {
            $k = $i + 1;
            if (!is_array($f) || !textoNoVacio($f['nombre'] ?? null) || !is_string($f['url'] ?? null) || !str_starts_with($f['url'], 'https://') || !fechaNoFutura($f['consultada'] ?? null) || ($f['coincide'] ?? null) !== true) {
                $e[] = "$donde: la fuente $k necesita nombre, url https://, consultada (AAAA-MM-DD, no posterior a hoy) y coincide: true.";
                continue;
            }
            $dominio = dominioPrincipal($f['url']);
            if ($dominio === '') {
                $e[] = "$donde: la fuente $k necesita una url con dominio.";
                continue;
            }
            $dominios[$dominio] = true;
        }
        if (count($dominios) < 2) {
            $e[] = "$donde: una cita verificada necesita al menos dos fuentes de sitios distintos que coincidan; se cuenta el dominio principal, sin «www.» ni subdominios (hay " . count($dominios) . ').';
        }
    }

    if (is_array($c['etiquetas'] ?? null) && textoNoVacio($c['texto'] ?? null)) {
        $unido = '';
        foreach ($c['etiquetas'] as $par) {
            if (!is_array($par) || count($par) !== 2 || !is_string($par[0]) || !in_array($par[1], UPOS, true)) {
                $e[] = "$donde: cada etiqueta debe ser [forma, categoría UPOS].";
                $unido = null;
                break;
            }
            $unido .= $par[0];
        }
        if ($unido !== null && $unido !== preg_replace('/\s+/u', '', (string) $c['texto'])) {
            $e[] = "$donde: las palabras de las etiquetas no reconstruyen el texto.";
        }
    }
    return $e;
}

function main(array $argv): int
{
    try {
        hoy(); // una CITAS_POS_HOY inválida se avisa aunque no haya ninguna fecha que comprobar
        $idiomas = array_column(leerJson(DATOS . '/idiomas.json')['idiomas'], null, 'codigo');
        $datos = leerJson($argv[1] ?? DATOS . '/citas.json');

        $errores = [];
        $ids = [];
        $porEstado = array_fill_keys(ESTADOS, 0);
        foreach ($datos['citas'] ?? [] as $i => $c) {
            $donde = 'cita ' . ($i + 1) . (isset($c['id']) && is_string($c['id']) ? " ({$c['id']})" : '');
            if (!is_array($c)) {
                $errores[] = "$donde: no es un objeto.";
                continue;
            }
            $faltan = array_diff(CAMPOS, array_keys($c));
            if ($faltan !== []) {
                $errores[] = "$donde: faltan campos: " . implode(', ', $faltan) . '.';
                continue;
            }
            if (!is_string($c['idioma']) || !isset($idiomas[$c['idioma']])) {
                $errores[] = "$donde: idioma desconocido «{$c['idioma']}» (código de 3 letras, ver datos/idiomas.json).";
                continue;
            }
            if (!is_string($c['id']) || !preg_match('/^[a-z]{3}-[a-z0-9]+\z/', $c['id']) || !str_starts_with($c['id'], $c['idioma'] . '-')) {
                $errores[] = "$donde: el id debe ser «{$c['idioma']}-» seguido de letras o números.";
            } elseif (isset($ids[$c['id']])) {
                $errores[] = "$donde: el id {$c['id']} está repetido.";
            }
            $ids[(string) $c['id']] = true;
            if (!in_array($c['estado'], ESTADOS, true)) {
                $errores[] = "$donde: estado desconocido «{$c['estado']}» (debe ser " . implode(', ', ESTADOS) . ').';
                continue;
            }
            $porEstado[$c['estado']]++;
            array_push($errores, ...errores($c, $donde, $idiomas[$c['idioma']]));
        }
    } catch (RuntimeException $error) {
        fwrite(STDERR, 'ERROR ' . $error->getMessage() . "\n");
        return 1;
    }

    foreach ($errores as $e) {
        echo "ERROR  $e\n";
    }
    $total = array_sum($porEstado);
    printf("%d cita(s): %d verificada(s), %d sin verificar, %d descartada(s).\n", $total, $porEstado['verificada'], $porEstado['sin-verificar'], $porEstado['descartada']);
    echo $errores === [] ? "Citas correctas.\n" : count($errores) . " error(es).\n";
    return $errores === [] ? 0 : 1;
}

exit(main($argv));
