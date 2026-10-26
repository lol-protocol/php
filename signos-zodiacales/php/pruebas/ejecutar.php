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

// Los casos que tienen que fallar, los mismos que prueba Python.
$conFecha = ['signo_occidental', 'cuspide', 'animal_chino_de_fecha'];
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
