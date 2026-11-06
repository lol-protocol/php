<?php

declare(strict_types=1);

/**
 * Pruebas de citas-pos, sin dependencias. Uso: php citas-pos/pruebas/ejecutar.php
 *
 * Corren los scripts de bin/ como procesos aparte. Los que leen datos/ de su propia carpeta
 * (paleta, prototipo, generador) se prueban sobre una copia temporal del proyecto, para poder
 * romper los datos sin tocar los reales. Los validadores de citas y de revisiones aceptan la ruta
 * del archivo, así que se prueban con archivos temporales.
 */

const RAIZ = __DIR__ . '/..';

require_once RAIZ . '/bin/lib/comun.php';

/** @var list<string> carpetas temporales por borrar al terminar */
$GLOBALS['temporales'] = [];

register_shutdown_function(static function (): void {
    foreach ($GLOBALS['temporales'] as $dir) {
        borrar($dir);
    }
});

function igual(mixed $esperado, mixed $obtenido): void
{
    if ($esperado !== $obtenido) {
        throw new RuntimeException('esperado ' . var_export($esperado, true) . ', obtenido ' . var_export($obtenido, true));
    }
}

function verdadero(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function borrar(string $ruta): void
{
    if (is_file($ruta) || is_link($ruta)) {
        unlink($ruta);
        return;
    }
    if (!is_dir($ruta)) {
        return;
    }
    foreach (scandir($ruta) ?: [] as $e) {
        if ($e !== '.' && $e !== '..') {
            borrar($ruta . '/' . $e);
        }
    }
    rmdir($ruta);
}

function copiar(string $origen, string $destino): void
{
    if (is_file($origen)) {
        copy($origen, $destino);
        return;
    }
    mkdir($destino, 0775, true);
    foreach (scandir($origen) ?: [] as $e) {
        if ($e !== '.' && $e !== '..') {
            copiar($origen . '/' . $e, $destino . '/' . $e);
        }
    }
}

function temporal(): string
{
    $dir = sys_get_temp_dir() . '/citas-pos-pruebas-' . bin2hex(random_bytes(6));
    mkdir($dir, 0775, true);
    $GLOBALS['temporales'][] = $dir;
    return $dir;
}

/** Una copia del proyecto para romperla: bin/, datos/, juego/, prototipo/, salida/ y revisiones.csv. */
function proyecto(): string
{
    $dir = temporal();
    foreach (['bin', 'datos', 'juego', 'prototipo', 'salida'] as $carpeta) {
        copiar(RAIZ . '/' . $carpeta, $dir . '/' . $carpeta);
    }
    copy(RAIZ . '/revisiones.csv', $dir . '/revisiones.csv');
    return $dir;
}

/**
 * @param list<string> $argumentos
 * @return array{int, string} código de salida y lo que escribió (salida y errores juntos)
 */
function correr(string $script, array $argumentos = []): array
{
    $orden = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    foreach ($argumentos as $a) {
        $orden .= ' ' . escapeshellarg($a);
    }
    exec($orden . ' 2>&1', $lineas, $codigo);
    return [$codigo, implode("\n", $lineas)];
}

/** @return array{int, string} */
function correr_node(string $archivo): array
{
    exec('node --check ' . escapeshellarg($archivo) . ' 2>&1', $lineas, $codigo);
    return [$codigo, implode("\n", $lineas)];
}

/** @param array{int, string} $r */
function pasa(array $r, string $trozo = ''): void
{
    verdadero($r[0] === 0, 'debía pasar y salió con código ' . $r[0] . ': ' . $r[1]);
    verdadero($trozo === '' || str_contains($r[1], $trozo), 'no dice «' . $trozo . '»: ' . $r[1]);
}

/** @param array{int, string} $r */
function rechaza(array $r, string $trozo): void
{
    verdadero($r[0] === 1, 'debía fallar con código 1 y salió con ' . $r[0] . ': ' . $r[1]);
    verdadero(str_contains($r[1], $trozo), 'no dice «' . $trozo . '»: ' . $r[1]);
}

/** @return array<string, mixed> */
function leerJson(string $ruta): array
{
    return json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
}

function escribirJson(string $ruta, mixed $datos): void
{
    file_put_contents($ruta, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}

// --- citas -------------------------------------------------------------------------------------

/** @param array<string, mixed> $cambios */
function cita(string $id, array $cambios = []): array
{
    return array_merge([
        'id' => $id, 'idioma' => 'spa', 'texto' => null, 'autor' => 'Autora de Prueba', 'fallecimiento' => 1800,
        'obra' => 'Obra de Prueba', 'ubicacion' => 'u', 'edicion' => null, 'fuentes' => [],
        'dominio_publico' => 'ok', 'tema' => ['t'], 'etiquetas' => null, 'estado' => 'sin-verificar',
        'por_comprobar' => ['x'],
    ], $cambios);
}

/** @return array<string, string|bool> */
function fuente(string $sitio): array
{
    return ['nombre' => 'S', 'url' => "https://$sitio/x", 'consultada' => '2026-10-26', 'coincide' => true];
}

const ETIQUETAS_DE_PRUEBA = [['Una', 'DET'], ['frase', 'NOUN'], ['de', 'ADP'], ['prueba', 'NOUN'], ['con', 'ADP'], ['seis', 'NUM'], ['palabras', 'NOUN']];

/** @param array<string, mixed> $cambios */
function verificada(string $id, array $cambios = []): array
{
    return cita($id, array_merge([
        'estado' => 'verificada', 'texto' => 'Una frase de prueba con seis palabras', 'edicion' => 'Ed. 1900',
        'fuentes' => [fuente('a.org'), fuente('b.org')],
    ], $cambios));
}

function palabrasDe(int $n): string
{
    return implode(' ', array_fill(0, $n, 'palabra'));
}

/** @param list<array<string, mixed>> $citas */
function validarCitas(array $citas): array
{
    $archivo = temporal() . '/citas.json';
    escribirJson($archivo, ['citas' => $citas]);
    return correr(RAIZ . '/bin/comprobar-citas.php', [$archivo]);
}

// --- revisiones --------------------------------------------------------------------------------

function validarRevisiones(string $filas, string $cabecera = 'idioma,alcance,elemento,veredicto,revisor,fecha,nota'): array
{
    $archivo = temporal() . '/revisiones.csv';
    file_put_contents($archivo, $cabecera . "\n" . $filas);
    return correr(RAIZ . '/bin/comprobar-revisiones.php', [$archivo]);
}

// --- pruebas -----------------------------------------------------------------------------------

$pruebas = [];

$pruebas['Los datos del repositorio pasan todos los comprobadores'] = function (): void {
    pasa(correr(RAIZ . '/bin/comprobar-paleta.php'), 'Paleta correcta');
    pasa(correr(RAIZ . '/bin/prototipo.php', ['--comprobar']), 'al día');
    pasa(correr(RAIZ . '/bin/render.php', ['--comprobar']), 'al día');
    pasa(correr(RAIZ . '/bin/comprobar-citas.php'), 'Citas correctas');
    pasa(correr(RAIZ . '/bin/comprobar-revisiones.php'), 'Registro correcto');
};

$pruebas['datos/idiomas.json tiene 30 idiomas, con códigos de 3 y de 2 letras únicos'] = function (): void {
    $idiomas = leerJson(RAIZ . '/datos/idiomas.json')['idiomas'];
    igual(30, count($idiomas));
    foreach (['codigo' => '/^[a-z]{3}$/', 'codigo2' => '/^[a-z]{2}$/'] as $campo => $patron) {
        $valores = array_column($idiomas, $campo);
        igual(30, count(array_unique($valores)));
        foreach ($valores as $v) {
            verdadero(preg_match($patron, $v) === 1, "$campo mal formado: $v");
        }
    }
};

// Citas: lo que debe pasar, incluidos los límites exactos.
$pasan = [
    'una cita verificada correcta, con etiquetas' => [verificada('spa-v1', ['etiquetas' => ETIQUETAS_DE_PRUEBA])],
    'el texto más corto permitido: 6 palabras' => [verificada('spa-v1', ['texto' => palabrasDe(6)])],
    'el texto más largo permitido: 20 palabras' => [verificada('spa-v1', ['texto' => palabrasDe(20)])],
    'un autor fallecido en 1925, el último año admitido' => [cita('spa-s1', ['fallecimiento' => 1925])],
    'una cita descartada de un autor reciente, con motivo' => [cita('spa-d1', ['estado' => 'descartada', 'fallecimiento' => 1976, 'motivo_descarte' => 'protegido'])],
    'una cita sin verificar, sin texto y con lo que falta' => [cita('spa-s1')],
];
// Chino: sin espacios, el largo se cuenta con las etiquetas que no son puntuación.
const ETIQUETAS_ZHO = [['春眠', 'NOUN'], ['不', 'ADV'], ['觉', 'VERB'], ['晓', 'NOUN'], ['，', 'PUNCT'], ['处处', 'ADV'], ['闻', 'VERB'], ['啼鸟', 'NOUN']];
function verificadaEnChino(array $cambios = []): array
{
    return verificada('zho-v1', array_merge(['idioma' => 'zho', 'texto' => '春眠不觉晓，处处闻啼鸟', 'etiquetas' => ETIQUETAS_ZHO], $cambios));
}
$pasan['una verificada en chino de 7 palabras (la coma no cuenta)'] = [verificadaEnChino()];
foreach ($pasan as $nombre => $citas) {
    $pruebas["comprobar-citas acepta $nombre"] = function () use ($citas): void {
        pasa(validarCitas($citas), 'Citas correctas');
    };
}

// Citas: cada regla que debe rechazar.
$sinAutor = cita('spa-m1');
unset($sinAutor['autor']);
$rechazan = [
    'una verificada con una sola fuente' => [[verificada('spa-m1', ['fuentes' => [fuente('a.org')]])], 'al menos dos fuentes de sitios distintos'],
    'una verificada con dos fuentes del mismo sitio' => [[verificada('spa-m1', ['fuentes' => [fuente('a.org'), fuente('a.org')]])], 'al menos dos fuentes de sitios distintos'],
    'una verificada sin fuentes' => [[verificada('spa-m1', ['fuentes' => []])], 'al menos dos fuentes de sitios distintos'],
    'una verificada con 5 palabras' => [[verificada('spa-m1', ['texto' => palabrasDe(5)])], 'el texto tiene 5 palabras'],
    'una verificada con 21 palabras' => [[verificada('spa-m1', ['texto' => palabrasDe(21)])], 'el texto tiene 21 palabras'],
    'una verificada sin texto' => [[verificada('spa-m1', ['texto' => null])], 'necesita su texto exacto'],
    'una verificada sin edición' => [[verificada('spa-m1', ['edicion' => ''])], 'necesita la edición'],
    'una fuente sin https' => [[verificada('spa-m1', ['fuentes' => [array_merge(fuente('a.org'), ['url' => 'http://a.org/x']), fuente('b.org')]])], 'la fuente 1 necesita'],
    'una fuente con fecha imposible' => [[verificada('spa-m1', ['fuentes' => [array_merge(fuente('a.org'), ['consultada' => '2026-13-40']), fuente('b.org')]])], 'la fuente 1 necesita'],
    'una fuente que no coincide' => [[verificada('spa-m1', ['fuentes' => [array_merge(fuente('a.org'), ['coincide' => false]), fuente('b.org')]])], 'la fuente 1 necesita'],
    'un autor fallecido en 1926' => [[cita('spa-m1', ['fallecimiento' => 1926])], 'murió en 1926'],
    'un autor fallecido en 1976' => [[cita('spa-m1', ['fallecimiento' => 1976])], 'murió en 1976'],
    'una cita sin verificar que no dice qué falta' => [[cita('spa-m1', ['por_comprobar' => []])], 'qué falta comprobar'],
    'una descartada sin motivo' => [[cita('spa-m1', ['estado' => 'descartada'])], 'necesita motivo_descarte'],
    'etiquetas que no reconstruyen el texto' => [[verificada('spa-m1', ['etiquetas' => [['Una', 'DET'], ['otra', 'NOUN']]])], 'no reconstruyen el texto'],
    'una categoría que no es UPOS' => [[verificada('spa-m1', ['etiquetas' => [['Una', 'ARTICULO']]])], 'cada etiqueta debe ser'],
    'etiquetas sin texto' => [[cita('spa-m1', ['etiquetas' => [['x', 'NOUN']]])], 'no puede tener etiquetas sin texto'],
    'un id de otro idioma' => [[cita('eng-m1')], 'el id debe ser'],
    'un idioma desconocido' => [[cita('xxx-m1', ['idioma' => 'xxx'])], 'idioma desconocido'],
    'un id repetido' => [[cita('spa-m1'), cita('spa-m1')], 'está repetido'],
    'un estado desconocido' => [[cita('spa-m1', ['estado' => 'quizá'])], 'estado desconocido'],
    'un tema vacío' => [[cita('spa-m1', ['tema' => []])], 'tema debe ser una lista'],
    'un dominio público sin explicar' => [[cita('spa-m1', ['dominio_publico' => ''])], 'dominio_publico debe explicar'],
    'un campo que falta' => [[$sinAutor], 'faltan campos: autor'],
];
$rechazan['una verificada en chino con 3 palabras'] = [[verificadaEnChino(['texto' => '春眠不', 'etiquetas' => [['春眠', 'NOUN'], ['不', 'ADV']]])], 'el texto tiene 2 palabras'];
foreach ($rechazan as $nombre => [$citas, $mensaje]) {
    $pruebas["comprobar-citas rechaza $nombre"] = function () use ($citas, $mensaje): void {
        rechaza(validarCitas($citas), $mensaje);
    };
}

// aprobadas(): lo que cuenta como revisado para el generador.
$pruebas['aprobadas: correcto y corregido aprueban'] = function (): void {
    $archivo = temporal() . '/r.csv';
    file_put_contents($archivo, "idioma,alcance,elemento,veredicto,revisor,fecha,nota\nspa,cita,spa-1,correcto,Ana,2026-10-26,\nspa,cita,spa-2,corregido,Ana,2026-10-26,\n");
    igual(['spa|cita|spa-1' => true, 'spa|cita|spa-2' => true], aprobadas($archivo));
};
$pruebas['aprobadas: un «corregir» o «dudoso» de otro revisor bloquea aunque haya un «correcto»'] = function (): void {
    $archivo = temporal() . '/r.csv';
    file_put_contents($archivo, "idioma,alcance,elemento,veredicto,revisor,fecha,nota\n"
        . "spa,cita,spa-1,correcto,Ana,2026-10-26,\nspa,cita,spa-1,corregir,Bob,2026-10-26,cambia una coma\n"
        . "spa,cita,spa-2,dudoso,Bob,2026-10-26,no sé\nspa,cita,spa-2,correcto,Ana,2026-10-26,\n"
        . "spa,cita,spa-3,correcto,Ana,2026-10-26,\n");
    igual(['spa|cita|spa-3' => true], aprobadas($archivo));
};

// Revisiones.
$pruebas['comprobar-revisiones acepta filas correctas, con comillas y comas en la nota'] = function (): void {
    pasa(validarRevisiones(
        "spa,etiquetas,spa-1,correcto,Ana,2026-10-26,\n"
        . "spa,etiquetas,spa-2,corregir,Ana,2026-10-26,\"«no» aquí es ADV, no PART\"\n"
        . "eng,etiquetas,eng-1,dudoso,Bob,2026-10-26,\"¿\"\"small\"\" ADJ o NOUN?\"\n"
        . "spa,cita,spa-c1,dudoso,Ana,2026-10-26,puntuación\n"
    ), '4 fila(s)');
};
$pruebas['comprobar-revisiones acepta una fecha de año bisiesto'] = function (): void {
    pasa(validarRevisiones("spa,etiquetas,spa-1,correcto,Ana,2028-02-29,\n"), '1 fila(s)');
};

$filasMalas = [
    'un idioma de 2 letras' => ["es,etiquetas,spa-1,correcto,Ana,2026-10-26,\n", 'idioma desconocido'],
    'un alcance desconocido' => ["spa,foo,spa-1,correcto,Ana,2026-10-26,\n", 'alcance desconocido'],
    'un elemento que no existe' => ["spa,etiquetas,spa-99,correcto,Ana,2026-10-26,\n", 'no existe para el alcance'],
    'una frase de prueba con alcance cita' => ["spa,cita,spa-1,correcto,Ana,2026-10-26,\n", 'no existe para el alcance'],
    'un elemento de otro idioma' => ["spa,etiquetas,eng-1,correcto,Ana,2026-10-26,\n", 'no es del idioma'],
    'un veredicto desconocido' => ["spa,etiquetas,spa-1,ok,Ana,2026-10-26,\n", 'veredicto desconocido'],
    'un revisor vacío' => ["spa,etiquetas,spa-1,correcto,,2026-10-26,\n", 'falta el revisor'],
    'una fecha imposible' => ["spa,etiquetas,spa-1,correcto,Ana,2026-13-01,\n", 'no es AAAA-MM-DD'],
    'el 29 de febrero de un año que no es bisiesto' => ["spa,etiquetas,spa-1,correcto,Ana,2026-02-29,\n", 'no es AAAA-MM-DD'],
    'corregir sin nota' => ["spa,etiquetas,spa-1,corregir,Ana,2026-10-26,\n", 'la nota es obligatoria'],
    'dudoso sin nota' => ["spa,etiquetas,spa-1,dudoso,Ana,2026-10-26,\n", 'la nota es obligatoria'],
    'dos filas del mismo revisor para lo mismo' => ["spa,etiquetas,spa-1,correcto,Ana,2026-10-26,\nspa,etiquetas,spa-1,correcto,Ana,2026-10-27,\n", 'ya hay una fila de'],
    'una fila con menos columnas' => ["spa,etiquetas,spa-1,correcto\n", 'tiene 4 columnas'],
];
foreach ($filasMalas as $nombre => [$filas, $mensaje]) {
    $pruebas["comprobar-revisiones rechaza $nombre"] = function () use ($filas, $mensaje): void {
        rechaza(validarRevisiones($filas), $mensaje);
    };
}
$pruebas['comprobar-revisiones acepta que dos revisores revisen lo mismo'] = function (): void {
    pasa(validarRevisiones("spa,etiquetas,spa-1,correcto,Ana,2026-10-26,\nspa,etiquetas,spa-1,correcto,Bob,2026-10-26,\n"), '2 fila(s)');
};
$pruebas['comprobar-revisiones rechaza una cabecera incorrecta'] = function (): void {
    rechaza(validarRevisiones("spa,etiquetas,spa-1,correcto,Ana,2026-10-26,\n", 'idioma,alcance,elemento'), 'Cabecera incorrecta');
};

$pruebas['GUIA-REVISION.md: las filas de ejemplo del CSV pasan el validador del registro'] = function (): void {
    $guia = (string) file_get_contents(RAIZ . '/GUIA-REVISION.md');
    verdadero(preg_match('/```csv\n(.+?)```/s', $guia, $m) === 1, 'no encuentra el bloque csv de la guía');
    $filas = preg_replace('/^[ \t]+/m', '', $m[1]);
    pasa(validarRevisiones((string) $filas), '3 fila(s)');
};

// Paleta, sobre una copia.
$cambiosDePaleta = [
    'un color con poco contraste' => [static function (array &$p): void {
        foreach ($p['categorias'] as &$c) {
            if ($c['codigo'] === 'NOUN') {
                $c['claro'] = '#9ec5e8';
            }
        }
    }, 'contraste'],
    'dos categorías con el mismo color' => [static function (array &$p): void {
        $adv = array_values(array_filter($p['categorias'], static fn (array $c): bool => $c['codigo'] === 'ADV'))[0]['claro'];
        foreach ($p['categorias'] as &$c) {
            if ($c['codigo'] === 'ADJ') {
                $c['claro'] = $adv;
            }
        }
    }, 'se parecen demasiado a simple vista'],
    'dos categorías con colores casi iguales, pero no idénticos' => [static function (array &$p): void {
        foreach ($p['categorias'] as &$c) {
            if ($c['codigo'] === 'ADJ') {
                $c['claro'] = '#667b1c'; // ADV es #4f7b1c: ΔE de unas unidades, lejos de los 18 exigidos
            }
        }
    }, 'se parecen demasiado a simple vista'],
    'dos grupos con la misma marca' => [static function (array &$p): void {
        $p['grupos']['verbal']['marca'] = $p['grupos']['nominal']['marca'];
    }, 'comparten la marca'],
    'una categoría que falta' => [static function (array &$p): void {
        $p['categorias'] = array_values(array_filter($p['categorias'], static fn (array $c): bool => $c['codigo'] !== 'X'));
    }, 'no son las 17 de UPOS'],
    'un color mal escrito' => [static function (array &$p): void {
        $p['categorias'][0]['oscuro'] = 'azul';
    }, 'no es #rrggbb'],
    'un grupo que no existe' => [static function (array &$p): void {
        $p['categorias'][0]['grupo'] = 'inventado';
    }, 'grupo desconocido'],
];
foreach ($cambiosDePaleta as $nombre => [$cambio, $mensaje]) {
    $pruebas["comprobar-paleta rechaza $nombre"] = function () use ($cambio, $mensaje): void {
        $dir = proyecto();
        $paleta = leerJson($dir . '/datos/paleta.json');
        $cambio($paleta);
        escribirJson($dir . '/datos/paleta.json', $paleta);
        rechaza(correr($dir . '/bin/comprobar-paleta.php'), $mensaje);
    };
}

// Prototipo, sobre una copia.
$pruebas['prototipo rechaza una palabra etiquetada que no está en el texto'] = function (): void {
    $dir = proyecto();
    $e = leerJson($dir . '/datos/ejemplos.json');
    $e['ejemplos'][0]['frases'][0]['palabras'][1][0] = 'gato';
    escribirJson($dir . '/datos/ejemplos.json', $e);
    rechaza(correr($dir . '/bin/prototipo.php'), 'no aparece en el texto');
};
$pruebas['prototipo rechaza texto sin etiquetar'] = function (): void {
    $dir = proyecto();
    $e = leerJson($dir . '/datos/ejemplos.json');
    $e['ejemplos'][0]['frases'][0]['texto'] = 'El gran perro pequeño lee despacio un libro rojo.';
    escribirJson($dir . '/datos/ejemplos.json', $e);
    rechaza(correr($dir . '/bin/prototipo.php'), 'texto sin etiquetar');
};
$pruebas['prototipo rechaza una categoría desconocida'] = function (): void {
    $dir = proyecto();
    $e = leerJson($dir . '/datos/ejemplos.json');
    $e['ejemplos'][0]['frases'][0]['palabras'][1][1] = 'FOO';
    escribirJson($dir . '/datos/ejemplos.json', $e);
    rechaza(correr($dir . '/bin/prototipo.php'), 'categoría desconocida');
};
$pruebas['prototipo --comprobar falla si el HTML guardado no está al día'] = function (): void {
    $dir = proyecto();
    file_put_contents($dir . '/prototipo/index.html', 'x', FILE_APPEND);
    rechaza(correr($dir . '/bin/prototipo.php', ['--comprobar']), 'no está al día');
    pasa(correr($dir . '/bin/prototipo.php'), 'Escrito');
    pasa(correr($dir . '/bin/prototipo.php', ['--comprobar']), 'al día');
};

// Generador, sobre una copia.
$pruebas['render --comprobar falla si un archivo cambia o sobra, y pasa tras regenerar'] = function (): void {
    $dir = proyecto();
    file_put_contents($dir . '/salida/ejemplos/spa-1.correo.html', 'x', FILE_APPEND);
    file_put_contents($dir . '/salida/ejemplos/suelto.html', 'x');
    $r = correr($dir . '/bin/render.php', ['--comprobar']);
    rechaza($r, 'spa-1.correo.html no está al día');
    verdadero(str_contains($r[1], 'suelto.html sobra'), 'no avisa del archivo sobrante: ' . $r[1]);
    pasa(correr($dir . '/bin/render.php'), 'Escritos');
    verdadero(!is_file($dir . '/salida/ejemplos/suelto.html'), 'no borró el archivo sobrante');
    pasa(correr($dir . '/bin/render.php', ['--comprobar']), 'al día');
};
$pruebas['render: el fragmento del correo no lleva estilos ni clases, enlaces, imágenes ni scripts'] = function (): void {
    $archivos = glob(RAIZ . '/salida/*/*.correo.html') ?: [];
    verdadero(count($archivos) >= 6, 'debía haber al menos 6 fragmentos de correo');
    foreach ($archivos as $ruta) {
        $html = (string) file_get_contents($ruta);
        foreach (['<style', 'class=', 'http', '<script', '<img', 'url('] as $prohibido) {
            verdadero(!str_contains($html, $prohibido), basename($ruta) . ' lleva ' . $prohibido);
        }
        verdadero(str_contains($html, 'style="'), basename($ruta) . ' no lleva estilos en línea');
    }
};
$pruebas['render rechaza una palabra etiquetada que no está en el texto'] = function (): void {
    $dir = proyecto();
    $e = leerJson($dir . '/datos/ejemplos.json');
    $e['ejemplos'][0]['frases'][0]['palabras'][1][0] = 'gato';
    escribirJson($dir . '/datos/ejemplos.json', $e);
    rechaza(correr($dir . '/bin/render.php'), 'no aparece en el texto');
};
$pruebas['render: los fragmentos del sitio llevan lang y la categoría de cada palabra'] = function (): void {
    $html = (string) file_get_contents(RAIZ . '/salida/ejemplos/spa-1.sitio.html');
    verdadero(str_contains($html, 'lang="es"'), 'falta lang="es"');
    verdadero(str_contains($html, 'data-c="NOUN"') && str_contains($html, 'data-c="VERB"'), 'faltan las categorías');
    verdadero(str_contains($html, 'Frase de prueba; no es una cita.'), 'falta el aviso de que no es una cita');
};

// «Adivina la categoría»: lo que se puede comprobar sin un navegador. El comportamiento (pulsar, teclado, leyenda
// que se llena) se probó a mano en Chromium; ver README.md.
function fragmentoDeJuego(string $id = 'spa-2'): string
{
    return (string) file_get_contents(RAIZ . "/salida/ejemplos/$id.juego.html");
}

$pruebas['juego: hay una palabra .jw por cada palabra etiquetada (sin la puntuación) y una entrada de leyenda por categoría'] = function (): void {
    $ejemplos = leerJson(RAIZ . '/datos/ejemplos.json');
    $n = 0;
    foreach ($ejemplos['ejemplos'] as $e) {
        foreach ($e['frases'] as $f) {
            $html = fragmentoDeJuego($f['id']);
            $palabras = array_filter($f['palabras'], static fn (array $p): bool => $p[1] !== 'PUNCT');
            $categorias = array_unique(array_column($palabras, 1));
            igual(count($palabras), substr_count($html, 'class="w jw"'));
            igual(count($categorias), substr_count($html, '<li data-c="'));
            igual(count($categorias), substr_count($html, 'class="w jm"'));
            $n++;
        }
    }
    verdadero($n >= 6, 'debía probar al menos 6 frases');
};
$pruebas['juego: sin JavaScript no se oculta nada (la ayuda y los controles esperan al script; nada finge ser un botón)'] = function (): void {
    $html = fragmentoDeJuego();
    verdadero(str_contains($html, 'class="jayuda" lang="es" hidden'), 'la ayuda debe llevar hidden hasta que haya script');
    verdadero(str_contains($html, 'class="jbar" lang="es" hidden'), 'los controles deben llevar hidden hasta que haya script');
    foreach (['aria-pressed', 'role="button"', 'tabindex', 'aria-label', 'class="citas-pos citas-pos-juego js'] as $prohibido) {
        verdadero(!str_contains($html, $prohibido), "el HTML estático no debe llevar $prohibido: lo pone el script");
    }
    verdadero(!preg_match('/<li[^>]*\shidden/', $html), 'la leyenda debe verse completa sin script');
};
$pruebas['juego: no regala la respuesta con un title, y la categoría de cada palabra va en data-nombre'] = function (): void {
    $html = fragmentoDeJuego();
    verdadero(!str_contains($html, 'title='), 'un title dejaría ver la categoría antes de descubrirla');
    verdadero(preg_match('/data-c="VERB"[^>]*data-nombre="verbo"/', $html) === 1, 'falta data-nombre="verbo" en la palabra de categoría VERB');
};
$pruebas['juego: la ayuda, la leyenda y los controles van en español y la frase en su idioma'] = function (): void {
    foreach (['spa-2' => 'es', 'eng-2' => 'en', 'deu-2' => 'de'] as $id => $lang) {
        $html = fragmentoDeJuego($id);
        verdadero(str_contains($html, "<figure class=\"citas-pos citas-pos-juego\" lang=\"$lang\">"), "$id: el fragmento debe llevar lang=\"$lang\"");
        foreach (['class="jayuda" lang="es"', 'class="jleyenda" lang="es"', 'class="jbar" lang="es"'] as $parte) {
            verdadero(str_contains($html, $parte), "$id: falta $parte");
        }
    }
};
$pruebas['juego: salida/ lleva la hoja y el script, iguales a las fuentes de juego/, y el demo no carga nada externo'] = function (): void {
    igual(file_get_contents(RAIZ . '/juego/juego.css'), file_get_contents(RAIZ . '/salida/citas-pos-juego.css'));
    igual(file_get_contents(RAIZ . '/juego/juego.js'), file_get_contents(RAIZ . '/salida/citas-pos-juego.js'));
    $demo = (string) file_get_contents(RAIZ . '/salida/demo-juego.html');
    foreach (['src=', 'href=', 'http', '@import', 'url('] as $externo) {
        verdadero(!str_contains($demo, $externo), "el demo no debe llevar $externo");
    }
    verdadero(substr_count($demo, '<figure class="citas-pos citas-pos-juego"') >= 6, 'el demo debe traer todas las frases');
};
$pruebas['juego: el JavaScript es válido (si hay node)'] = function (): void {
    exec('command -v node 2>/dev/null', $salida, $codigo);
    if ($codigo !== 0) {
        return;
    }
    $r = correr_node(RAIZ . '/juego/juego.js');
    verdadero($r[0] === 0, 'node --check falló: ' . $r[1]);
};
$pruebas['render rechaza un juego.js que cierre el script del demo'] = function (): void {
    $dir = proyecto();
    file_put_contents($dir . '/juego/juego.js', "\n// </script>\n", FILE_APPEND);
    rechaza(correr($dir . '/bin/render.php'), 'no puede contener');
};

// La puerta de revisión: una cita real solo sale con su texto y sus etiquetas aprobados.
/** @return array{string, array<string, mixed>} */
function proyectoConCita(): array
{
    $dir = proyecto();
    $c = leerJson($dir . '/datos/citas.json');
    $c['citas'][] = verificada('spa-v1', ['texto' => "Una frase de prueba con\nseis palabras", 'etiquetas' => ETIQUETAS_DE_PRUEBA]);
    escribirJson($dir . '/datos/citas.json', $c);
    return [$dir, $c];
}

$casosDePuerta = [
    'sin ninguna revisión' => ['', false],
    'con solo la revisión del texto' => ["spa,cita,spa-v1,correcto,Ana,2026-10-26,\n", false],
    'con solo la revisión de las etiquetas' => ["spa,etiquetas,spa-v1,correcto,Ana,2026-10-26,\n", false],
    'con una revisión dudosa en el texto' => ["spa,cita,spa-v1,dudoso,Ana,2026-10-26,duda\nspa,etiquetas,spa-v1,correcto,Ana,2026-10-26,\n", false],
    'con una revisión «corregir» en las etiquetas' => ["spa,cita,spa-v1,correcto,Ana,2026-10-26,\nspa,etiquetas,spa-v1,corregir,Ana,2026-10-26,cambiar algo\n", false],
    'con las dos aprobadas como correctas' => ["spa,cita,spa-v1,correcto,Ana,2026-10-26,\nspa,etiquetas,spa-v1,correcto,Ana,2026-10-26,\n", true],
    'con las dos aprobadas, una de ellas ya corregida' => ["spa,cita,spa-v1,correcto,Ana,2026-10-26,\nspa,etiquetas,spa-v1,corregido,Ana,2026-10-26,\n", true],
];
foreach ($casosDePuerta as $nombre => [$filas, $sale]) {
    $pruebas["render " . ($sale ? 'genera' : 'no genera') . " una cita verificada $nombre"] = function () use ($filas, $sale): void {
        [$dir] = proyectoConCita();
        file_put_contents($dir . '/revisiones.csv', $filas, FILE_APPEND);
        $r = correr($dir . '/bin/render.php');
        pasa($r);
        $hay = is_file($dir . '/salida/citas/spa-v1.sitio.html') && is_file($dir . '/salida/citas/spa-v1.correo.html') && is_file($dir . '/salida/citas/spa-v1.juego.html');
        if ($sale) {
            verdadero($hay, 'debía generar la cita y no lo hizo: ' . $r[1]);
            $sitio = (string) file_get_contents($dir . '/salida/citas/spa-v1.sitio.html');
            verdadero(str_contains($sitio, '— Autora de Prueba, <i>Obra de Prueba</i>'), 'falta el autor y la obra: ' . $sitio);
            verdadero(str_contains($sitio, '<br>'), 'el salto de verso no pasó a <br>');
            verdadero(!str_contains($r[1], 'falta su revisión'), 'no debía avisar de revisión pendiente');
        } else {
            verdadero(!$hay, 'generó una cita sin revisión completa');
            verdadero(str_contains($r[1], 'falta su revisión en revisiones.csv: spa-v1'), 'no avisó de la revisión pendiente: ' . $r[1]);
        }
    };
}
$pruebas['render no genera una cita sin verificar aunque tenga todas las revisiones'] = function (): void {
    $dir = proyecto();
    $c = leerJson($dir . '/datos/citas.json');
    $c['citas'][] = cita('spa-s9', ['texto' => 'Una frase de prueba con seis palabras', 'etiquetas' => ETIQUETAS_DE_PRUEBA]);
    escribirJson($dir . '/datos/citas.json', $c);
    file_put_contents($dir . '/revisiones.csv', "spa,cita,spa-s9,correcto,Ana,2026-10-26,\nspa,etiquetas,spa-s9,correcto,Ana,2026-10-26,\n", FILE_APPEND);
    pasa(correr($dir . '/bin/render.php'));
    verdadero(!is_file($dir . '/salida/citas/spa-s9.sitio.html'), 'generó una cita que no está verificada');
    verdadero(!is_file($dir . '/salida/citas/spa-s9.juego.html'), 'generó el juego de una cita que no está verificada');
};

$fallidas = 0;
foreach ($pruebas as $nombre => $cuerpo) {
    try {
        $cuerpo();
        echo "ok     $nombre\n";
    } catch (Throwable $error) {
        $fallidas++;
        echo "FALLO  $nombre\n       " . $error->getMessage() . "\n";
    }
}

echo count($pruebas) - $fallidas . ' de ' . count($pruebas) . " pruebas pasan.\n";
exit($fallidas === 0 ? 0 : 1);
