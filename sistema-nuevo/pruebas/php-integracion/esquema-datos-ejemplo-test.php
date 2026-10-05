<?php

declare(strict_types=1);

/** @var PDO $pdo */

// esquema-datos-ejemplo.sql son INSERT sueltos que el sistema no carga solo, así que
// nada los ejercitaba. Se prueban en un schema descartable dentro de una transacción
// que SIEMPRE se revierte (el DDL de PostgreSQL es transaccional): no toca las
// tablas reales ni deja nada, pase lo que pase. Con search_path apuntando solo al
// schema descartable, los DROP TABLE IF EXISTS de esquema.sql no ven las tablas reales.
$datos = __DIR__ . '/../../datos';

// La cabecera tiene que decir qué cargar antes: solo con esquema.sql el ejemplo falla
// ('relation "usuarios" does not exist'), porque usuarios/administradores/acciones
// viven en esquema-nucleo.sql.
$cabecera = implode("\n", array_slice(file("$datos/esquema-datos-ejemplo.sql", FILE_IGNORE_NEW_LINES), 0, 8));
foreach (['esquema.sql', 'esquema-nucleo.sql'] as $requerido) {
    assert_verdadero(str_contains($cabecera, $requerido), "ejemplo SQL: la cabecera nombra $requerido (hay que cargarlo antes)");
}

$error = null;
$pdo->beginTransaction();
try {
    $pdo->exec('CREATE SCHEMA ejemplo_sql_prueba');
    $pdo->exec('SET LOCAL search_path TO ejemplo_sql_prueba');

    foreach (['esquema.sql', 'esquema-nucleo.sql', 'esquema-datos-ejemplo.sql'] as $archivo) {
        $pdo->exec((string) file_get_contents("$datos/$archivo"));
    }

    $contar = fn (string $tabla): int => (int) $pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
    assert_igual(5, $contar('acciones'), 'ejemplo SQL: carga las 5 acciones de ejemplo (una de cada tipo)');
    assert_igual(2, $contar('usuarios'), 'ejemplo SQL: carga los 2 usuarios de ejemplo');
    assert_igual(1, $contar('administradores'), 'ejemplo SQL: carga el admin de ejemplo');

    $hash = (string) $pdo->query("SELECT clave_hash FROM administradores WHERE usuario = 'admin'")->fetchColumn();
    assert_verdadero(password_verify('admin123', $hash), 'ejemplo SQL: el hash del admin de ejemplo es de "admin123", como dice su comentario');
} catch (PDOException $e) {
    $error = $e->getMessage();
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
assert_igual(null, $error, 'ejemplo SQL: esquema.sql + esquema-nucleo.sql + esquema-datos-ejemplo.sql cargan sin error, en ese orden');

$existe = $pdo->query("SELECT COUNT(*) FROM pg_namespace WHERE nspname = 'ejemplo_sql_prueba'")->fetchColumn();
assert_igual(0, (int) $existe, 'ejemplo SQL: el rollback no deja el schema descartable');
assert_verdadero(
    (int) $pdo->query('SELECT COUNT(*) FROM public.acciones')->fetchColumn() > 0,
    'ejemplo SQL: las tablas reales quedaron intactas'
);
