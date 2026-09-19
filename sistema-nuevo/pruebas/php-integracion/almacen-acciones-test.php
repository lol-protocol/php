<?php

declare(strict_types=1);

/**
 * Nada en el schema impide que dos acciones del mismo usuario empaten en
 * marca_temporal (el índice usuario_id+marca_temporal no es único), así que
 * pagina() necesita un desempate determinístico o el orden relativo entre
 * empatadas -- y por lo tanto qué página termina mostrando a cada una --
 * puede cambiar según el orden físico de las filas, no algo bajo control.
 *
 * @var PDO $pdo
 */
$almacen = new AlmacenAcciones($pdo);
$userId = $pdo->query("SELECT id FROM usuarios LIMIT 1")->fetchColumn();
assert_verdadero($userId !== false, 'acciones: hay al menos un usuario sembrado para probar contra él');

$insertar = function (PDO $pdo, string $id, string $userId) {
    $stmt = $pdo->prepare(
        'INSERT INTO acciones (id, usuario_id, tipo_clave, marca_temporal, duracion_ms, ruta, ip)
         VALUES (:id, :userId, \'login\', :ts, 100, \'/test\', \'1.2.3.4\')'
    );
    $stmt->execute(['id' => $id, 'userId' => $userId, 'ts' => '2020-06-15 12:00:00']);
};
$limpiar = fn (PDO $pdo) => $pdo->exec("DELETE FROM acciones WHERE id IN ('zTEST1', 'zTEST2')");

$idsDelUsuario = function (array $items): array {
    return array_values(array_filter(
        array_map(fn ($it) => $it['id'], $items),
        fn ($id) => str_starts_with($id, 'zTEST')
    ));
};

$limpiar($pdo);
$insertar($pdo, 'zTEST1', $userId);
$insertar($pdo, 'zTEST2', $userId);
$pagina = $almacen->pagina($userId, null, 1, 100);
$ordenA = $idsDelUsuario($pagina['items']);
$limpiar($pdo);

$insertar($pdo, 'zTEST2', $userId);
$insertar($pdo, 'zTEST1', $userId);
$pagina = $almacen->pagina($userId, null, 1, 100);
$ordenB = $idsDelUsuario($pagina['items']);
$limpiar($pdo);

assert_igual(['zTEST1', 'zTEST2'], $ordenA, 'acciones: dos filas empatadas en marca_temporal se ordenan por id');
assert_igual(
    $ordenA,
    $ordenB,
    'acciones: el orden entre empatadas no depende del orden físico de inserción (desempate por id)'
);
