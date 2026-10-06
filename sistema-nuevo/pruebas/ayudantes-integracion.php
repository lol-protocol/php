<?php

declare(strict_types=1);

/**
 * Ayudantes de las pruebas de integración (las que corren contra PostgreSQL): los carga ejecutar-integracion.php.
 */

/**
 * Corre $fn dentro de un schema descartable, en una transacción que SIEMPRE se revierte (el DDL de PostgreSQL es
 * transaccional): no toca las tablas reales ni deja nada, pase lo que pase. Con el search_path apuntando solo al schema
 * descartable, los DROP TABLE IF EXISTS de esquema.sql no ven las tablas reales. Sirve para probar los esquemas SQL.
 *
 * @return string|null El mensaje del error de PostgreSQL si $fn (o armar el schema) falló, o null si salió bien.
 */
function en_schema_descartable(PDO $pdo, string $schema, callable $fn): ?string
{
    if (preg_match('/^[a-z_]+$/', $schema) !== 1) {
        throw new InvalidArgumentException("Nombre de schema no permitido: $schema");
    }

    $error = null;
    $pdo->beginTransaction();
    try {
        $pdo->exec("CREATE SCHEMA $schema");
        $pdo->exec("SET LOCAL search_path TO $schema");
        $fn();
    } catch (PDOException $e) {
        $error = $e->getMessage();
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    return $error;
}

function schema_existe(PDO $pdo, string $schema): bool
{
    $consulta = $pdo->prepare('SELECT COUNT(*) FROM pg_namespace WHERE nspname = ?');
    $consulta->execute([$schema]);
    return (int) $consulta->fetchColumn() > 0;
}
