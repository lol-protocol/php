<?php

declare(strict_types=1);

/**
 * ¿Hace falta sembrar la base? Sembrar es destructivo: esquema.sql y esquema-nucleo.sql
 * hacen DROP de TODAS las tablas, también las que el usuario escribe desde el panel
 * (notas_acciones, filtros_guardados, configuracion_alertas) y el contador de bloqueo de
 * login (intentos_login). Por eso ejecutar.sh siembra con sembrar-si-falta.php, que solo
 * lo hace cuando la base no está sembrada.
 *
 * "Sembrada" = existen todas las tablas que crean los dos esquemas Y hay acciones
 * cargadas (cargar_en_postgres las inserta al final, dentro de una transacción: si la
 * carga se cortó, no quedan). Si falta una tabla es que el esquema cambió desde la última
 * siembra; no hay migraciones, así que la única salida es recrearlo todo.
 *
 * @return string|null Por qué hay que sembrar, o null si la base ya está sembrada.
 */
function motivo_para_sembrar(PDO $pdo, string $dirDatos): ?string
{
    $esperadas = [];
    foreach (['esquema.sql', 'esquema-nucleo.sql'] as $archivo) {
        preg_match_all('/^\s*CREATE\s+TABLE\s+([a-z_][a-z0-9_]*)/im', (string) file_get_contents("$dirDatos/$archivo"), $coincidencias);
        $esperadas = [...$esperadas, ...$coincidencias[1]];
    }
    if ($esperadas === []) {
        // Mejor fallar fuerte que decidir "está vacía" y recrear (o "está bien") sin saber.
        throw new RuntimeException("No se encontró ningún CREATE TABLE en $dirDatos/esquema*.sql");
    }

    $existe = $pdo->prepare('SELECT to_regclass(?)');
    $faltan = [];
    foreach ($esperadas as $tabla) {
        $existe->execute([$tabla]);
        if ($existe->fetchColumn() === null) {
            $faltan[] = $tabla;
        }
    }

    if (count($faltan) === count($esperadas)) {
        return 'la base está vacía';
    }
    if ($faltan !== []) {
        return 'el esquema cambió (faltan las tablas ' . implode(', ', $faltan) . '): se recrea todo, '
            . 'y eso borra las notas, los filtros guardados y la configuración de alertas';
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM acciones')->fetchColumn() === 0) {
        return 'no hay acciones cargadas';
    }
    return null;
}

/** Avisa por STDERR qué va a pasar y devuelve si hay que sembrar. */
function hay_que_sembrar(PDO $pdo, string $dirDatos): bool
{
    $motivo = motivo_para_sembrar($pdo, $dirDatos);
    if ($motivo === null) {
        fwrite(STDERR, "Los datos semilla ya están en PostgreSQL: no se vuelven a sembrar, así se conservan las notas, "
            . "los filtros guardados y la configuración de alertas. Para recrearlos desde cero: ./ejecutar.sh --regenerar\n");
        return false;
    }
    fwrite(STDERR, "Sembrando los datos: $motivo.\n");
    return true;
}
