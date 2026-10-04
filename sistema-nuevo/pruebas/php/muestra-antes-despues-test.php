<?php

declare(strict_types=1);

require_once __DIR__ . '/../../datos/generador/sanear-acciones.php';

// datos/ejemplos/muestra-antes-despues.json es documentación escrita a mano ("así
// queda cada tipo de inconsistencia después del saneador"): si el saneador cambia y
// nadie actualiza el ejemplo, miente sin que nada avise. Se corre el saneador real
// -- con el mismo contexto que arma el generador (sanear_acciones) -- sobre cada
// "crudo", y tiene que dar el "saneado" documentado (o null si se descarta).
$datos = __DIR__ . '/../../datos';
$monedas = require "$datos/generador/catalogo-monedas.php";
$red = require "$datos/generador/catalogo-red.php";
$tipos = (require "$datos/generador/tipos-accion.php")['tiposAccion'];
$usuariosPorId = array_column(json_decode((string) file_get_contents("$datos/usuarios.json"), true), null, 'id');

$muestra = json_decode((string) file_get_contents("$datos/ejemplos/muestra-antes-despues.json"), true);
assert_igual(7, count($muestra), 'muestra: son los 7 registros que documenta el README');

foreach ($muestra as $par) {
    $resultado = sanear_acciones(
        [$par['crudo']],
        $usuariosPorId,
        $monedas['currencyByCountry'],
        $monedas['rateToUsd'],
        $tipos,
        $red['offsetPorPais']
    );
    assert_igual(
        $par['saneado'],
        $resultado['acciones'][0] ?? null,
        "muestra {$par['crudo']['id']}: el saneador da lo que documenta muestra-antes-despues.json"
    );
}
