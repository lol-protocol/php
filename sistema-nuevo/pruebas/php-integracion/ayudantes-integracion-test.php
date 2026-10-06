<?php

declare(strict_types=1);

/** @var PDO $pdo */

// en_schema_descartable() es lo que usan las pruebas que cargan los esquemas SQL sin tocar las tablas reales. Si dejara
// de revertir, o se tragara un error de PostgreSQL, esas pruebas ensuciarían la base o pasarían sin probar nada.
$tablaReal = 'acciones';
$vistas = [];

$error = en_schema_descartable($pdo, 'ayudante_prueba', function () use ($pdo, &$vistas, $tablaReal) {
    $pdo->exec('CREATE TABLE solo_aca (id INT)');
    $vistas['propia'] = $pdo->query("SELECT to_regclass('solo_aca')")->fetchColumn();
    $vistas['real'] = $pdo->query("SELECT to_regclass('$tablaReal')")->fetchColumn();
});
assert_igual(null, $error, 'en_schema_descartable: sin error devuelve null');
assert_verdadero($vistas['propia'] !== null, 'en_schema_descartable: adentro se ve lo que se crea');
assert_igual(null, $vistas['real'], 'en_schema_descartable: adentro NO se ven las tablas reales (el search_path es solo el descartable)');
assert_verdadero(!schema_existe($pdo, 'ayudante_prueba'), 'en_schema_descartable: al terminar no queda el schema');
assert_verdadero(schema_existe($pdo, 'public'), 'schema_existe: ve un schema que sí existe');
assert_verdadero((int) $pdo->query("SELECT COUNT(*) FROM public.$tablaReal")->fetchColumn() > 0, 'en_schema_descartable: las tablas reales quedan intactas');

$error = en_schema_descartable($pdo, 'ayudante_prueba', fn () => $pdo->exec('SELECT * FROM una_tabla_que_no_existe'));
assert_verdadero(is_string($error) && str_contains($error, 'una_tabla_que_no_existe'), 'en_schema_descartable: un error de PostgreSQL vuelve como texto, no se traga');
assert_verdadero(!schema_existe($pdo, 'ayudante_prueba'), 'en_schema_descartable: también revierte cuando algo falló');
assert_verdadero(!$pdo->inTransaction(), 'en_schema_descartable: no deja una transacción abierta');

$rechazado = false;
try {
    en_schema_descartable($pdo, 'x; DROP TABLE acciones', fn () => null);
} catch (InvalidArgumentException) {
    $rechazado = true;
}
assert_verdadero($rechazado, 'en_schema_descartable: rechaza un nombre de schema que no sea solo letras minúsculas y guiones bajos');
