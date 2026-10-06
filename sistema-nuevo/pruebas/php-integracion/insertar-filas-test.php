<?php

declare(strict_types=1);

/** @var PDO $pdo */

require_once __DIR__ . '/../../datos/generador/insertar-filas.php';

// insertar_filas() es lo que usan todos los cargar_* de la siembra: una fila por elemento, cada columna con su valor.
// Se prueba sobre una tabla propia en un schema descartable que se revierte.
$leido = [];
$sinFilas = null;
$conflicto = ['con' => null, 'sin' => null];
$rechazados = [];

$error = en_schema_descartable($pdo, 'insertar_prueba', function () use ($pdo, &$leido, &$conflicto, &$rechazados) {
    $pdo->exec('CREATE TABLE cosas (id INTEGER PRIMARY KEY, nombre VARCHAR(20) NOT NULL, nota TEXT NULL)');

    insertar_filas($pdo, 'cosas', []);
    insertar_filas($pdo, 'cosas', [
        ['id' => 2, 'nombre' => 'segunda', 'nota' => null],
        ['id' => 1, 'nombre' => 'primera', 'nota' => 'con nota'],
        ['id' => 3, 'nombre' => 'tercera', 'nota' => null],
    ]);
    $leido = $pdo->query('SELECT id, nombre, nota FROM cosas ORDER BY id')->fetchAll(PDO::FETCH_NUM);

    // $alConflicto: con "ON CONFLICT DO NOTHING" repetir una fila no falla ni la duplica; sin él, sí falla.
    insertar_filas($pdo, 'cosas', [['id' => 1, 'nombre' => 'repetida', 'nota' => null]], 'ON CONFLICT (id) DO NOTHING');
    $conflicto['con'] = $pdo->query('SELECT nombre FROM cosas WHERE id = 1')->fetchColumn();
    $pdo->exec('SAVEPOINT antes_del_duplicado');
    try {
        insertar_filas($pdo, 'cosas', [['id' => 1, 'nombre' => 'repetida', 'nota' => null]]);
        $conflicto['sin'] = 'no falló';
    } catch (PDOException) {
        $conflicto['sin'] = 'falló';
        $pdo->exec('ROLLBACK TO SAVEPOINT antes_del_duplicado');
    }

    // Los nombres tienen que parecer los del esquema, no una inyección.
    foreach (['cosas; DROP TABLE cosas', 'Cosas'] as $tablaRara) {
        try {
            insertar_filas($pdo, $tablaRara, [['id' => 9]]);
        } catch (InvalidArgumentException) {
            $rechazados[] = $tablaRara;
        }
    }
    try {
        insertar_filas($pdo, 'cosas', [['id) VALUES (1); --' => 9]]);
    } catch (InvalidArgumentException) {
        $rechazados[] = 'columna rara';
    }
});

assert_igual(null, $error, 'insertar_filas: se corre sin error en el schema descartable');
assert_igual(
    [[1, 'primera', 'con nota'], [2, 'segunda', null], [3, 'tercera', null]],
    $leido,
    'insertar_filas: una fila por elemento, cada columna con su valor (también NULL), y una lista vacía no hace nada'
);
assert_igual(['primera', 'falló'], [$conflicto['con'], $conflicto['sin']], 'insertar_filas: $alConflicto va al final del INSERT; sin él, repetir una clave falla');
assert_igual(['cosas; DROP TABLE cosas', 'Cosas', 'columna rara'], $rechazados, 'insertar_filas: rechaza una tabla o una columna que no parezca un nombre del esquema');
