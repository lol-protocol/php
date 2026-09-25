<?php

declare(strict_types=1);

/**
 * Mismo problema que AlmacenAcciones::pagina() (ver almacen-acciones-test.php):
 * nada impide que dos usuarios compartan nombre, así que usersPage() necesita
 * un desempate determinístico en el ORDER BY o el orden relativo entre
 * empatados no está garantizado.
 *
 * @var PDO $pdo
 */
$almacen = new AlmacenDatos($pdo);
$paisCodigo = $pdo->query('SELECT codigo FROM paises LIMIT 1')->fetchColumn();
assert_verdadero($paisCodigo !== false, 'datos: hay al menos un país sembrado para probar contra él');

$insertar = function (PDO $pdo, string $id, string $paisCodigo) {
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (id, nombre, pais_codigo, edad, genero) VALUES (:id, :nombre, :pais, 30, \'O\')'
    );
    $stmt->execute(['id' => $id, 'nombre' => 'Zeta Empatada de Prueba', 'pais' => $paisCodigo]);
};
$limpiar = fn (PDO $pdo) => $pdo->exec("DELETE FROM usuarios WHERE id IN ('zT01', 'zT02')");

$idsDePrueba = function (array $items): array {
    return array_values(array_filter(
        array_map(fn ($it) => $it['id'], $items),
        fn ($id) => str_starts_with($id, 'zT0')
    ));
};

$limpiar($pdo);
$insertar($pdo, 'zT01', $paisCodigo);
$insertar($pdo, 'zT02', $paisCodigo);
$resultado = $almacen->usersPage(1, 100, 'Zeta Empatada de Prueba');
$ordenA = $idsDePrueba($resultado['items']);
$limpiar($pdo);

$insertar($pdo, 'zT02', $paisCodigo);
$insertar($pdo, 'zT01', $paisCodigo);
$resultado = $almacen->usersPage(1, 100, 'Zeta Empatada de Prueba');
$ordenB = $idsDePrueba($resultado['items']);
$limpiar($pdo);

assert_igual(['zT01', 'zT02'], $ordenA, 'datos: dos usuarios empatados en nombre se ordenan por id');
assert_igual(
    $ordenA,
    $ordenB,
    'datos: el orden entre usuarios empatados no depende del orden físico de inserción (desempate por id)'
);
