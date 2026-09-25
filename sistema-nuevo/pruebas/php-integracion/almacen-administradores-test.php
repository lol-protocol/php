<?php

declare(strict_types=1);

/** @var PDO $pdo */
$almacen = new AlmacenAdministradores($pdo);

assert_verdadero($almacen->claveHash('admin') !== null, 'administradores: el admin semilla existe en la tabla');
assert_igual(null, $almacen->claveHash('__usuario_inexistente__'), 'administradores: usuario inexistente devuelve null');

assert_verdadero(auth_verificar_credenciales($pdo, 'admin', 'admin123'), 'auth: admin/admin123 (semilla) autentica contra la tabla');
assert_igual(false, auth_verificar_credenciales($pdo, 'admin', 'contraseña_incorrecta'), 'auth: admin con contraseña incorrecta no autentica');
assert_igual(false, auth_verificar_credenciales($pdo, '__usuario_inexistente__', 'cualquiera'), 'auth: usuario inexistente no autentica (sin romper con el hash dummy)');

// La prueba de fondo: un admin que SOLO existe en la tabla (no en credenciales.php)
// tiene que poder loguearse -- si el login todavía leyera del archivo estático, esto
// fallaría aunque AlmacenAdministradores ya ande bien.
$usuarioTest = '__test_admin__';
$claveHashTest = '$2y$12$yHgFkER1.1IypFQenafbWu19R88p1VeYomFgXiXEKaUsfkHGe7gVe'; // password_hash('clave_test_regresion_123', ...)

$pdo->prepare('DELETE FROM administradores WHERE usuario = ?')->execute([$usuarioTest]); // por si quedó sucio de una corrida anterior interrumpida
$pdo->prepare('INSERT INTO administradores (usuario, clave_hash) VALUES (?, ?)')->execute([$usuarioTest, $claveHashTest]);

assert_verdadero(
    auth_verificar_credenciales($pdo, $usuarioTest, 'clave_test_regresion_123'),
    'auth: un admin que solo existe en la tabla (no en credenciales.php) autentica -- prueba que el login lee de la BD, no del archivo'
);
assert_igual(
    false,
    auth_verificar_credenciales($pdo, $usuarioTest, 'clave_incorrecta'),
    'auth: el admin nuevo con contraseña incorrecta no autentica'
);

$pdo->prepare('DELETE FROM administradores WHERE usuario = ?')->execute([$usuarioTest]);
assert_igual(null, $almacen->claveHash($usuarioTest), 'administradores: cleanup borró al admin de prueba');
