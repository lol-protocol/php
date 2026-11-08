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

/*
 * La búsqueda es por texto, no por patrón: un «%», un «_» o una «\» que se tipea es un carácter, no un comodín de
 * ILIKE. Sin escapar, «%» listaba a todos los usuarios y «Gamma_Prueba» encontraba también «Gamma Prueba».
 */
$usuariosDeComodin = [
    ['zT03', 'Cien% Exacto de Prueba'],
    ['zT04', 'Cienx Exacto de Prueba'],
    ['zT05', 'Gamma_Prueba'],
    ['zT06', 'Gamma Prueba'],
    ['zT07', 'Atras\\Barra de Prueba'],
];
$limpiarComodines = fn (PDO $pdo) => $pdo->exec("DELETE FROM usuarios WHERE id IN ('zT03', 'zT04', 'zT05', 'zT06', 'zT07')");
$limpiarComodines($pdo);
foreach ($usuariosDeComodin as [$id, $nombre]) {
    $pdo->prepare('INSERT INTO usuarios (id, nombre, pais_codigo, edad, genero) VALUES (:id, :nombre, :pais, 30, \'O\')')
        ->execute(['id' => $id, 'nombre' => $nombre, 'pais' => $paisCodigo]);
}
$nombresEncontrados = fn (string $busqueda): array => array_values(array_filter(
    array_map(fn ($it) => $it['name'], $almacen->usersPage(1, 100, $busqueda)['items']),
    fn ($nombre) => str_contains($nombre, 'Prueba')
));

assert_igual(['Cien% Exacto de Prueba'], $nombresEncontrados('Cien%'), 'datos: «%» en la búsqueda es un porcentaje, no un comodín');
assert_igual(['Gamma_Prueba'], $nombresEncontrados('Gamma_Prueba'), 'datos: «_» en la búsqueda es un guion bajo, no «cualquier carácter»');
assert_igual(['Atras\\Barra de Prueba'], $nombresEncontrados('Atras\\Barra'), 'datos: «\\» en la búsqueda es una barra invertida, no el escape de LIKE');
assert_igual(['Cien% Exacto de Prueba'], $nombresEncontrados('%'), 'datos: buscar solo «%» devuelve a quien tiene un «%» en el nombre, no a todos');
$conGuionBajo = $almacen->usersPage(1, 100, '_');
assert_verdadero($conGuionBajo['total'] < 10, 'datos: buscar solo «_» no devuelve a todos los usuarios (total ' . $conGuionBajo['total'] . ')');
$todos = $almacen->usersPage(1, 100, '');
assert_verdadero($todos['total'] >= 60, 'datos: sin búsqueda se listan todos');
$limpiarComodines($pdo);
