<?php

declare(strict_types=1);

/**
 * Inserta en $tabla una fila por cada elemento de $filas. Cada fila es un array "columna => valor", todas con las mismas
 * columnas: los nombres de las columnas quedan junto a sus valores en quien llama (se lee qué columna recibe qué), y acá
 * se arma el INSERT con un parámetro con nombre por columna y se ejecuta una vez por fila. $alConflicto va al final de la
 * sentencia si se pasa (p. ej. "ON CONFLICT (usuario) DO NOTHING").
 *
 * La tabla y las columnas son los nombres de siempre del esquema, nunca datos de afuera: igual se exige que lo parezcan.
 *
 * @param array<int,array<string,mixed>> $filas
 */
function insertar_filas(PDO $pdo, string $tabla, array $filas, string $alConflicto = ''): void
{
    if ($filas === []) {
        return;
    }

    $columnas = array_keys($filas[array_key_first($filas)]);
    foreach ([$tabla, ...$columnas] as $nombre) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', (string) $nombre) !== 1) {
            throw new InvalidArgumentException("Nombre de tabla o columna no permitido: $nombre");
        }
    }

    $stmt = $pdo->prepare(trim(sprintf(
        'INSERT INTO %s (%s) VALUES (%s) %s',
        $tabla,
        implode(', ', $columnas),
        implode(', ', array_map(static fn (string $columna): string => ":$columna", $columnas)),
        $alConflicto
    )));
    foreach ($filas as $fila) {
        $stmt->execute($fila);
    }
}
