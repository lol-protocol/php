<?php

declare(strict_types=1);

/**
 * Valida datos/citas.json: que cada cita tenga la ficha completa de CRITERIOS.md y que ninguna
 * figure como «verificada» sin texto exacto, edición y dos fuentes independientes.
 *
 *   php citas-pos/bin/comprobar-citas.php            # valida citas-pos/datos/citas.json
 *   php citas-pos/bin/comprobar-citas.php otro.json  # valida otro archivo (para probar)
 *
 * No comprueba que el texto coincida con la fuente (eso es trabajo de una persona, y queda
 * anotado en la ficha): comprueba que la ficha diga lo que hace falta para poder comprobarlo.
 */

const ESTADOS = ['sin-verificar', 'verificada', 'descartada'];
const UPOS = ['ADJ', 'ADP', 'ADV', 'AUX', 'CCONJ', 'DET', 'INTJ', 'NOUN', 'NUM', 'PART', 'PRON', 'PROPN', 'PUNCT', 'SCONJ', 'SYM', 'VERB', 'X'];
const CAMPOS = ['id', 'idioma', 'texto', 'autor', 'fallecimiento', 'obra', 'ubicacion', 'edicion', 'fuentes', 'dominio_publico', 'tema', 'etiquetas', 'estado'];
const ULTIMO_ANIO_DE_FALLECIMIENTO = 1925; // vida+100 ya cumplida en 2026; ver CRITERIOS.md
const PALABRAS_MIN = 6;
const PALABRAS_MAX = 20;

function fechaValida(mixed $f): bool
{
    if (!is_string($f)) {
        return false;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $f);
    return $d !== false && $d->format('Y-m-d') === $f;
}

function textoNoVacio(mixed $v): bool
{
    return is_string($v) && trim($v) !== '';
}

/**
 * @param array<string, mixed> $c
 * @return list<string>
 */
function errores(array $c, string $donde, string $idioma): array
{
    $e = [];
    $estado = $c['estado'];

    if (!textoNoVacio($c['autor'] ?? null) || !textoNoVacio($c['obra'] ?? null) || !textoNoVacio($c['ubicacion'] ?? null)) {
        $e[] = "$donde: autor, obra y ubicacion son obligatorios.";
    }
    if (!is_int($c['fallecimiento'] ?? null)) {
        $e[] = "$donde: fallecimiento debe ser un año (entero).";
    } elseif ($c['fallecimiento'] > ULTIMO_ANIO_DE_FALLECIMIENTO && $estado !== 'descartada') {
        $e[] = "$donde: el autor murió en {$c['fallecimiento']}; solo entran fallecidos hasta " . ULTIMO_ANIO_DE_FALLECIMIENTO . ' (o la cita queda «descartada»).';
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
            $n = count(preg_split('/\s+/u', trim($c['texto'])) ?: []);
            if ($n < PALABRAS_MIN || $n > PALABRAS_MAX) {
                $e[] = "$donde: el texto tiene $n palabras; debe tener entre " . PALABRAS_MIN . ' y ' . PALABRAS_MAX . '.';
            }
        }
        if (!textoNoVacio($c['edicion'])) {
            $e[] = "$donde: una cita verificada necesita la edición de la que sale.";
        }
        $hosts = [];
        foreach ($c['fuentes'] as $i => $f) {
            $k = $i + 1;
            if (!is_array($f) || !textoNoVacio($f['nombre'] ?? null) || !is_string($f['url'] ?? null) || !str_starts_with($f['url'], 'https://') || !fechaValida($f['consultada'] ?? null) || ($f['coincide'] ?? null) !== true) {
                $e[] = "$donde: la fuente $k necesita nombre, url https://, consultada (AAAA-MM-DD) y coincide: true.";
                continue;
            }
            $hosts[(string) parse_url($f['url'], PHP_URL_HOST)] = true;
        }
        if (count($hosts) < 2) {
            $e[] = "$donde: una cita verificada necesita al menos dos fuentes de sitios distintos que coincidan (hay " . count($hosts) . ').';
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
    $base = __DIR__ . '/../datos/';
    $ruta = $argv[1] ?? $base . 'citas.json';
    $idiomas = array_column(json_decode((string) file_get_contents($base . 'idiomas.json'), true, 512, JSON_THROW_ON_ERROR)['idiomas'], 'codigo');
    $datos = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);

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
        if (!in_array($c['idioma'], $idiomas, true)) {
            $errores[] = "$donde: idioma desconocido «{$c['idioma']}» (código de 3 letras, ver datos/idiomas.json).";
            continue;
        }
        if (!is_string($c['id']) || !preg_match('/^[a-z]{3}-[a-z0-9]+$/', $c['id']) || !str_starts_with($c['id'], $c['idioma'] . '-')) {
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
        array_push($errores, ...errores($c, $donde, $c['idioma']));
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
