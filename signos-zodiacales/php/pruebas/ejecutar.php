<?php

declare(strict_types=1);

/**
 * Pruebas del port a PHP, sin dependencias. Uso, desde esta carpeta: php pruebas/ejecutar.php
 *
 * Casi todo lo comprueba contra datos/vectores.json, que genera generar.py con zodiaco.py: si PHP y Python
 * difieren en una sola de esas respuestas, falla. Aparte van los ejemplos del README y los casos de error.
 */

require __DIR__ . '/../src/Zodiaco.php';

use SignosZodiacales\Zodiaco;

$fallos = [];
$comprobaciones = 0;

function igual(mixed $esperado, mixed $obtenido, string $donde): void
{
    global $fallos, $comprobaciones;
    $comprobaciones++;
    if ($esperado !== $obtenido) {
        $fallos[] = "$donde: esperado " . json_encode($esperado, JSON_UNESCAPED_UNICODE) . ', obtenido ' . json_encode($obtenido, JSON_UNESCAPED_UNICODE);
    }
}

function lanza(callable $f, string $donde): void
{
    global $fallos, $comprobaciones;
    $comprobaciones++;
    try {
        $f();
        $fallos[] = "$donde: tendría que lanzar una excepción";
    } catch (Throwable) {
        // bien
    }
}

function dia(string $mesDia): DateTimeImmutable
{
    return new DateTimeImmutable("2024-$mesDia", new DateTimeZone('UTC')); // 2024 es bisiesto
}

function ids(?array $signos): ?array
{
    return $signos === null ? null : array_map(fn (array $s): string => $s['id'], $signos);
}

$vectores = json_decode(file_get_contents(__DIR__ . '/../../datos/vectores.json'), true, 512, JSON_THROW_ON_ERROR);

// La versión de los datos.
igual($vectores['version'], Zodiaco::version(), 'version');
igual(1, preg_match('/^\d+\.\d+\.\d+$/', Zodiaco::version()), 'version con forma X.Y.Z');

// Los signos de cada día del año bisiesto, en los dos sistemas.
foreach ($vectores['signo_occidental'] as $sistema => $lista) {
    igual(366, count($lista), "signo_occidental/$sistema: días");
    foreach ($lista as [$mesDia, $id]) {
        igual($id, Zodiaco::signoOccidental(dia($mesDia), $sistema)['id'], "signo_occidental $sistema $mesDia");
    }
}

foreach ($vectores['cuspide'] as [$mesDia, $esperado]) {
    igual($esperado, ids(Zodiaco::cuspide(dia($mesDia))), "cuspide $mesDia");
    igual($esperado !== null, Zodiaco::esCuspide(dia($mesDia)), "es_cuspide $mesDia");
}

foreach ($vectores['animal_chino'] as [$anio, $id, $hanzi, $elemento, $polaridad]) {
    $a = Zodiaco::animalChino($anio);
    igual([$id, $hanzi, $elemento, $polaridad], [$a['signo']['id'], $a['tronco']['hanzi'], $a['elemento_del_anio'], $a['polaridad']], "animal_chino $anio");
}

// Las fechas alrededor de cada Año Nuevo lunar (el día antes y el día).
foreach ($vectores['animal_chino_de_fecha'] as [$fecha, $anioLunar]) {
    igual($anioLunar, Zodiaco::animalChinoDeFecha(new DateTimeImmutable($fecha, new DateTimeZone('UTC')))['anio_lunar'], "animal_chino_de_fecha $fecha");
}

// Instantes con huso: se pasan a la hora de China.
foreach ($vectores['animal_chino_de_instante'] as [$texto, $anioLunar, $id]) {
    $a = Zodiaco::animalChinoDeInstante(new DateTimeImmutable($texto));
    igual([$anioLunar, $id], [$a['anio_lunar'], $a['signo']['id']], "animal_chino_de_instante $texto");
}

foreach ($vectores['nombre_del_anio'] as $idioma => $nombres) {
    foreach ($nombres as $n => $esperado) {
        igual($esperado, Zodiaco::nombreDelAnio(2020 + $n, $idioma), "nombre_del_anio $idioma " . (2020 + $n));
    }
}

foreach ($vectores['compatibilidad'] as [$a, $b, $relaciones, $afinidad]) {
    igual(['relaciones' => $relaciones, 'afinidad' => $afinidad], Zodiaco::compatibilidad($a, $b), "compatibilidad $a $b");
}

foreach ($vectores['decanato'] as $sistema => $lista) {
    foreach ($lista as [$mesDia, $id, $numero, $regente, $signoRegente]) {
        $d = Zodiaco::decanato(dia($mesDia), $sistema);
        igual([$id, $numero, $regente, $signoRegente], [$d['signo']['id'], $d['numero'], $d['regente'], $d['signo_regente']['id']], "decanato $sistema $mesDia");
    }
}

foreach ($vectores['regentes_de_decanatos'] as $id => $regentes) {
    igual($regentes, Zodiaco::regentesDeDecanatos($id), "regentes_de_decanatos $id");
}

$occidentales = array_column(array_filter(Zodiaco::cargarDatos()['signos'], fn (array $s): bool => $s['tipo'] === 'occidental'), null, 'id');
foreach ($vectores['tramos_de_decanatos'] as $sistema => $porSigno) {
    foreach ($porSigno as $id => $tramos) {
        igual($tramos, Zodiaco::tramosDeDecanatos($occidentales[$id], $sistema), "tramos_de_decanatos $sistema $id");
    }
}

foreach ($vectores['anios_del_animal'] as $id => $anios) {
    igual($anios, Zodiaco::aniosDelAnimal($id, 1900, 2099), "anios_del_animal $id");
}

foreach ($vectores['revision'] as $idioma => $estado) {
    igual($estado, Zodiaco::revision($idioma), "revision $idioma");
}

// Los 72 ángeles y los 72 demonios: el de cada día del año bisiesto, con su coro y su tramo.
igual(366, count($vectores['espiritu_de_fecha']), 'espiritu_de_fecha: días');
foreach ($vectores['espiritu_de_fecha'] as [$mesDia, $numero, $idAngel, $coro, $idDemonio, $tramo]) {
    $a = Zodiaco::angelDeFecha(dia($mesDia));
    $d = Zodiaco::demonioDeFecha(dia($mesDia));
    igual([$numero, $idAngel, $coro, $tramo], [$a['angel']['numero'], $a['angel']['id'], $a['coro'], $a['tramo']], "angel_de_fecha $mesDia");
    igual([$numero, $idDemonio, $tramo], [$d['demonio']['numero'], $d['demonio']['id'], $d['tramo']], "demonio_de_fecha $mesDia");
}

foreach ($vectores['tramos_de_espiritus'] as $id => $tramos) {
    igual($tramos, Zodiaco::tramosDeEspiritus($id), "tramos_de_espiritus $id");
}

// Las cualidades: las muestras de cada idioma, las 144 en español y la huella de las 144 de cada idioma.
foreach ($vectores['cualidades_de'] as $idioma => $filas) {
    foreach ($filas as [$tipo, $numero, $texto]) {
        igual($texto, Zodiaco::cualidadesDe($tipo, $numero, $idioma), "cualidades_de $tipo $numero $idioma");
    }
}
foreach ($vectores['cualidades_de_sha256'] as $idioma => $huella) {
    $frases = [];
    foreach (Zodiaco::TIPOS_DE_ESPIRITU as $tipo) {
        for ($n = 1; $n <= 72; $n++) {
            $frases[] = Zodiaco::cualidadesDe($tipo, $n, $idioma);
        }
    }
    igual($huella, hash('sha256', implode("\n", $frases)), "cualidades_de (las 144) $idioma");
}

// Los árboles: el mes celta y el árbol del horóscopo de cada día del año bisiesto.
igual(366, count($vectores['arbol_celta']), 'arbol_celta: días');
foreach ($vectores['arbol_celta'] as [$mesDia, $esperado]) {
    $c = Zodiaco::arbolCelta(dia($mesDia));
    igual($esperado, $c === null ? null : [$c['mes']['id'], $c['arbol']['id']], "arbol_celta $mesDia");
}
foreach ($vectores['arbol_de_fecha'] as [$mesDia, $idArbol, $tramo]) {
    $h = Zodiaco::arbolDeFecha(dia($mesDia));
    igual([$idArbol, $tramo], [$h['arbol']['id'], $h['tramo']], "arbol_de_fecha $mesDia");
}

// La Luna: el nakshatra de 300 instantes entre 1900 y 2099 y su longitud sideral (las dos lenguas calculan en coma flotante,
// por eso la longitud se compara con una tolerancia de una millonésima de grado y el número, exacto).
foreach ($vectores['nakshatra_de_instante'] as [$texto, $numero, $longitud]) {
    $n = Zodiaco::nakshatraDeInstante(new DateTimeImmutable($texto));
    igual($numero, $n['nakshatra']['numero'], "nakshatra_de_instante $texto");
    $comprobaciones++;
    if (abs($n['longitud_sideral'] - $longitud) > 1e-5) {
        $fallos[] = "nakshatra_de_instante $texto: longitud " . $n['longitud_sideral'] . ", esperada $longitud";
    }
}

// Los rasgos: las muestras de cada idioma, los 61 en español y la huella de los 61 de cada idioma.
foreach ($vectores['rasgos_de'] as $idioma => $filas) {
    foreach ($filas as [$sistema, $clave, $texto]) {
        igual($texto, Zodiaco::rasgosDe($sistema, $clave, $idioma), "rasgos_de $sistema $clave $idioma");
    }
}
$claves = $vectores['claves_de_arboles']; // las 61, tal como las enumera generar.py: el port no las reconstruye
igual(13 + 21 + 27, count($claves), 'claves_de_arboles: son 61');
foreach ($vectores['rasgos_de_sha256'] as $idioma => $huella) {
    $frases = array_map(fn (array $k): string => Zodiaco::rasgosDe($k[0], $k[1], $idioma), $claves);
    igual($huella, hash('sha256', implode("\n", $frases)), "rasgos_de (los 61) $idioma");
}

// La numerología: el camino de vida de 1 377 fechas, el año personal, la reducción y los rasgos de los 12 números.
foreach ($vectores['camino_de_vida'] as [$fecha, $esperado]) {
    igual($esperado, Zodiaco::caminoDeVida(new DateTimeImmutable($fecha, new DateTimeZone('UTC'))), "camino_de_vida $fecha");
}
foreach ($vectores['anio_personal'] as [$fecha, $anio, $esperado]) {
    igual($esperado, Zodiaco::anioPersonal(new DateTimeImmutable($fecha, new DateTimeZone('UTC')), $anio), "anio_personal $fecha $anio");
}
foreach ($vectores['reducir_numero'] as [$n, $conMaestros, $sinMaestros]) {
    igual([$conMaestros, $sinMaestros], [Zodiaco::reducirNumero($n), Zodiaco::reducirNumero($n, false)], "reducir_numero $n");
}
foreach ($vectores['rasgos_de_numero'] as $idioma => $filas) {
    foreach ($filas as [$numero, $texto]) {
        igual($texto, Zodiaco::rasgosDeNumero($numero, $idioma), "rasgos_de_numero $numero $idioma");
    }
}

// Los tramos no dependen del año: el decanato y los 72 son los mismos en un bisiesto (2024) y en un año normal (2025),
// y el 29/02 cuenta con el 28/02. Antes el 10/03/2024 daba el decanato 3 y el 10/03/2025, el 2.
$utc = new DateTimeZone('UTC');
foreach (Zodiaco::SISTEMAS as $sistema) {
    for ($n = 0; $n < 365; $n++) {
        $d25 = (new DateTimeImmutable('2025-01-01', $utc))->modify("+$n days");
        $d24 = new DateTimeImmutable('2024-' . $d25->format('m-d'), $utc);
        igual(Zodiaco::decanato($d25, $sistema)['numero'], Zodiaco::decanato($d24, $sistema)['numero'], 'decanato igual en bisiesto ' . $sistema . ' ' . $d25->format('m-d'));
    }
}
for ($n = 0; $n < 365; $n++) {
    $d25 = (new DateTimeImmutable('2025-01-01', $utc))->modify("+$n days");
    $d24 = new DateTimeImmutable('2024-' . $d25->format('m-d'), $utc);
    igual(Zodiaco::angelDeFecha($d25)['tramo'], Zodiaco::angelDeFecha($d24)['tramo'], 'ángel igual en bisiesto ' . $d25->format('m-d'));
}
igual(
    Zodiaco::angelDeFecha(new DateTimeImmutable('2025-02-28', $utc))['angel']['numero'],
    Zodiaco::angelDeFecha(new DateTimeImmutable('2024-02-29', $utc))['angel']['numero'],
    'el 29/02 cuenta con el 28/02',
);
igual(2, Zodiaco::decanato(new DateTimeImmutable('2024-03-10', $utc))['numero'], 'el 10/03/2024 ya no cambia de decanato');
$piscis = array_values(array_filter(Zodiaco::cargarDatos()['signos'], fn (array $x): bool => $x['id'] === 'piscis'))[0];
igual(Zodiaco::tramosDeDecanatos($piscis), Zodiaco::tramosDeDecanatos($piscis, 'tropical', 2024), 'tramosDeDecanatos ignora el año obsoleto');
igual(Zodiaco::tramosDeEspiritus('piscis'), Zodiaco::tramosDeEspiritus('piscis', 2024), 'tramosDeEspiritus ignora el año obsoleto');
igual([[[2, 19], [2, 28]], [[3, 1], [3, 10]], [[3, 11], [3, 20]]], Zodiaco::tramosDeDecanatos($piscis), 'tramos de Piscis');

// Una clave que no está en ningún glosario lanza una excepción clara (antes se descartaba en silencio).
$frase = new ReflectionMethod(Zodiaco::class, 'frase');
lanza(fn () => $frase->invoke(null, ['inventada'], 'es', [[]], ['es' => ', ']), 'frase con una clave sin glosario');
igual('Uno, dos', $frase->invoke(null, ['x', 'y'], 'es', [['x' => ['es' => 'uno']], ['y' => ['es' => 'dos']]], ['es' => ', ']), 'frase busca en varios glosarios');

// Los arrays de PHP se copian al pasarlos: modificar lo que devuelve una consulta no toca las siguientes.
$a = Zodiaco::arbolCelta(new DateTimeImmutable('2025-06-10', $utc));
$a['mes']['rasgos'][] = 'X';
$a['arbol']['nombres'] = [];
$b = Zodiaco::arbolCelta(new DateTimeImmutable('2025-06-11', $utc));
igual(false, in_array('X', $b['mes']['rasgos'], true), 'modificar el resultado no corrompe los datos');
igual(true, count($b['arbol']['nombres']) === 30, 'los nombres siguen en los 30 idiomas');

// Los casos que tienen que fallar, los mismos que prueba Python.
$conFecha = ['signo_occidental', 'cuspide', 'animal_chino_de_fecha', 'angel_de_fecha', 'demonio_de_fecha', 'arbol_celta', 'arbol_de_fecha', 'camino_de_vida', 'anio_personal'];
foreach ($vectores['errores'] as $caso) {
    $args = $caso['args'];
    if (in_array($caso['funcion'], $conFecha, true)) {
        $args[0] = new DateTimeImmutable($args[0], new DateTimeZone('UTC'));
    }
    $metodo = lcfirst(str_replace('_', '', ucwords($caso['funcion'], '_')));
    lanza(fn () => Zodiaco::$metodo(...$args), 'error esperado en ' . json_encode($caso, JSON_UNESCAPED_UNICODE));
}

// Ejemplos del README, a mano y en el orden en que se leen.
$u = new DateTimeZone('UTC');
igual('aries', Zodiaco::signoOccidental(new DateTimeImmutable('1990-04-03', $u))['id'], 'ejemplo: Aries');
igual('piscis', Zodiaco::signoOccidental(new DateTimeImmutable('1990-04-03', $u), 'sideral')['id'], 'ejemplo: Piscis sideral');
$caballo = Zodiaco::animalChino(2026);
igual('caballo', $caballo['signo']['id'], 'ejemplo: 2026 es Caballo');
igual('fuego', $caballo['elemento_del_anio'], 'ejemplo: 2026 es de Fuego');
igual('丙午', $caballo['tronco']['hanzi'] . $caballo['signo']['rama']['hanzi'], 'ejemplo: 2026 es 丙午');
igual('serpiente', Zodiaco::animalChinoDeFecha(new DateTimeImmutable('2026-02-16', $u))['signo']['id'], 'ejemplo: 16/02/2026 aún es Serpiente');
igual('Caballo de Fuego', Zodiaco::nombreDelAnio(2026, 'es'), 'ejemplo: nombre en español');
igual('火马', Zodiaco::nombreDelAnio(2026, 'zh'), 'ejemplo: nombre en chino');
igual('alta', Zodiaco::compatibilidad('rata', 'dragon')['afinidad'], 'ejemplo: rata y dragón');
igual('baja', Zodiaco::compatibilidad('rata', 'caballo')['afinidad'], 'ejemplo: rata y caballo');
// Ejemplos de los ángeles y demonios, como los del README.
$tres = new DateTimeImmutable('1990-04-03', $u);
igual(['Sitael', 'serafines', [[3, 31], [4, 4]]], [Zodiaco::angelDeFecha($tres)['angel']['nombre'], Zodiaco::angelDeFecha($tres)['coro'], Zodiaco::angelDeFecha($tres)['tramo']], 'ejemplo: ángel del 3 de abril');
igual('Vassago', Zodiaco::demonioDeFecha($tres)['demonio']['nombre'], 'ejemplo: demonio del 3 de abril');
igual('Construcción, protección, nobleza', Zodiaco::cualidadesDe('angel', 3, 'es'), 'ejemplo: cualidades de Sitael');
igual('İrade, başlangıçlar, enerji', Zodiaco::cualidadesDe('angel', 1, 'tr'), 'ejemplo: la i con punto en turco');
// Ejemplos de los árboles y la Luna, como los del README.
igual(['Fearn', 'aliso'], [Zodiaco::arbolCelta($tres)['mes']['ogham'], Zodiaco::arbolCelta($tres)['arbol']['nombres']['es']], 'ejemplo: mes celta del 3 de abril');
igual(null, Zodiaco::arbolCelta(new DateTimeImmutable('2025-12-23', $u)), 'ejemplo: el 23 de diciembre no tiene árbol');
igual('serbal', Zodiaco::arbolDeFecha($tres)['arbol']['nombres']['es'], 'ejemplo: árbol del horóscopo del 3 de abril');
$luna = Zodiaco::nakshatraDeInstante(new DateTimeImmutable('1992-04-12T00:00:00+00:00'));
igual(['Ashlesha', 'Nagkesar'], [$luna['nakshatra']['nombre'], $luna['nakshatra']['arbol']], 'ejemplo: nakshatra del 12/04/1992');
igual('Equilibrio, sabiduría, paz', Zodiaco::rasgosDe('horoscopo', 'olivo', 'es'), 'ejemplo: rasgos del olivo');
// Ejemplos de la numerología, como los del README.
igual([8, 11, 22, 33], array_map(fn (string $f): int => Zodiaco::caminoDeVida(new DateTimeImmutable($f, $u)), ['1990-04-03', '1980-01-19', '1980-01-03', '1980-04-29']), 'ejemplo: caminos de vida');
igual(8, Zodiaco::anioPersonal($tres, 2026), 'ejemplo: año personal');
igual('Intuición, inspiración, idealismo', Zodiaco::rasgosDeNumero(11, 'es'), 'ejemplo: rasgos del 11');
// Un datetime con huso en animalChinoDeFecha cuenta por su día local, no por el de China.
igual('serpiente', Zodiaco::animalChinoDeFecha(new DateTimeImmutable('2026-02-16T20:00:00+00:00'))['signo']['id'], 'ejemplo: el día local manda en animalChinoDeFecha');
igual('caballo', Zodiaco::animalChinoDeInstante(new DateTimeImmutable('2026-02-16T20:00:00+00:00'))['signo']['id'], 'ejemplo: el instante pasa a la hora de China');
// El mismo instante da lo mismo en cualquier huso.
igual(
    Zodiaco::animalChinoDeInstante(new DateTimeImmutable('2026-02-17T07:00:00-08:00'))['anio_lunar'],
    Zodiaco::animalChinoDeInstante(new DateTimeImmutable('2026-02-17T15:00:00+00:00', new DateTimeZone('Asia/Tokyo')))['anio_lunar'],
    'ejemplo: el mismo instante en dos husos',
);

if ($fallos !== []) {
    echo count($fallos) . " fallos de $comprobaciones comprobaciones:\n";
    foreach (array_slice($fallos, 0, 40) as $fallo) {
        echo "  $fallo\n";
    }
    if (count($fallos) > 40) {
        echo '  … y ' . (count($fallos) - 40) . " más\n";
    }
    exit(1);
}
echo "Bien: $comprobaciones comprobaciones.\n";
