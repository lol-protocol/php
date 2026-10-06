<?php

declare(strict_types=1);

/** @var PDO $pdo */

// Las reglas de las dos alertas, con datos CONTROLADOS en vez de la semilla: sobre la semilla, el número de
// usuarios con un cambio de país imposible casi no se mueve con el umbral (1 hasta una ventana de 3.3 h, 2
// después), así que ni un cambio en la fórmula ni en el borde de la comparación se notaría. Se insertan tres
// usuarios propios, con huecos de tiempo puestos a propósito en los bordes, dentro de una transacción que SIEMPRE
// se revierte, y se mide cuánto SUMAN a lo que ya hay: la diferencia es exacta aunque la semilla cambie, porque
// son usuarios nuevos con su propia línea de tiempo.
//
// La ventana de "imposible" va de 0.5 h (umbral 0) a 4 h (umbral 100): 0.5 + umbral/100 * 3.5, y el hueco tiene
// que ser ESTRICTAMENTE menor.
[$paisA, $paisB] = array_column($pdo->query('SELECT codigo FROM paises ORDER BY codigo LIMIT 2')->fetchAll(), 'codigo');
$tipo = (string) $pdo->query("SELECT clave FROM tipos_accion WHERE clave = 'login'")->fetchColumn();
assert_igual('login', $tipo, 'alertas-reglas: hay un tipo de acción login para armar las acciones de prueba');

$alertas = new AlmacenAlertas($pdo);
$config = new AlmacenConfiguracion($pdo);
$kpis = fn (): int => (new AlmacenKpis($pdo))->resumen()['active_alerts_users'];

// umbral => cuántos de los tres usuarios de prueba tienen un cambio imposible con esa ventana.
$esperadoPorUmbral = [
    0 => 1,   // ventana 0.500 h: solo z901 (hueco de 0.4 h)
    50 => 1,  // ventana 2.250 h: z902 tiene un hueco de EXACTAMENTE 2.25 h, y el borde no cuenta
    51 => 2,  // ventana 2.285 h: ahora sí z902
    90 => 2,  // ventana 3.650 h: z903 (3.9 h) todavía no
    98 => 3,  // ventana 3.930 h: z903 entra
    100 => 3, // ventana 4.000 h
];

$insertarAccion = $pdo->prepare(
    "INSERT INTO acciones (id, usuario_id, tipo_clave, marca_temporal, duracion_ms, ruta, ip, ip_pais_codigo)
     VALUES (:id, :usuario, :tipo, :cuando, 100, '/prueba', '10.0.0.1', :ip_pais)"
);
$acciones = [
    // z901: A -> B en 24 minutos (0.4 h); en medio una acción sin país de IP, que ninguna regla debe tomar en cuenta.
    ['z90101', 'z901', '2026-01-01 10:00:00', $paisA],
    ['z90102', 'z901', '2026-01-01 10:24:00', $paisB],
    ['z90103', 'z901', '2026-01-01 10:30:00', null],
    // z902: A -> B en exactamente 2 h 15 min (2.25 h).
    ['z90201', 'z902', '2026-01-01 12:00:00', $paisA],
    ['z90202', 'z902', '2026-01-01 14:15:00', $paisB],
    // z903: A -> B en 3 h 54 min (3.9 h).
    ['z90301', 'z903', '2026-01-01 08:00:00', $paisA],
    ['z90302', 'z903', '2026-01-01 11:54:00', $paisB],
];

// La tarjeta del timeline marca ip_mismatch con la versión en PHP de la regla de IP: tiene que contar lo mismo que la
// versión en SQL del panel de alertas, sobre todas las acciones de la semilla.
$paisesPorAccion = $pdo->query('SELECT a.ip_pais_codigo, u.pais_codigo FROM acciones a JOIN usuarios u ON u.id = a.usuario_id')->fetchAll();
$fueraDelPaisEnPhp = count(array_filter($paisesPorAccion, fn ($f) => AlmacenAlertas::esIpFueraDelPais($f['ip_pais_codigo'], $f['pais_codigo'])));
assert_igual($alertas->ipMismatches()['total_mismatches'], $fueraDelPaisEnPhp, 'alertas: la regla de IP en PHP (ip_mismatch de la tarjeta) cuenta lo mismo que la de SQL');
assert_igual(
    [false, false, true],
    [AlmacenAlertas::esIpFueraDelPais(null, 'AR'), AlmacenAlertas::esIpFueraDelPais('AR', 'AR'), AlmacenAlertas::esIpFueraDelPais('CH', 'AR')],
    'alertas: una IP sin país o del país declarado no es "fuera del país"; de otro país, sí'
);

$error = null;
$pdo->beginTransaction();
try {
    $antes = [];
    foreach (array_keys($esperadoPorUmbral) as $umbral) {
        $antes[$umbral] = $alertas->cambiosPaisImposibles($umbral);
    }
    $ipAntes = $alertas->ipMismatches();

    $insertarUsuario = $pdo->prepare("INSERT INTO usuarios (id, nombre, pais_codigo, edad, genero) VALUES (?, ?, ?, 30, 'O')");
    foreach (['z901', 'z902', 'z903'] as $usuario) {
        $insertarUsuario->execute([$usuario, "Usuario de prueba $usuario", $paisA]);
    }
    foreach ($acciones as [$id, $usuario, $cuando, $ipPais]) {
        $insertarAccion->execute(['id' => $id, 'usuario' => $usuario, 'tipo' => $tipo, 'cuando' => $cuando, 'ip_pais' => $ipPais]);
    }

    foreach ($esperadoPorUmbral as $umbral => $usuariosEsperados) {
        $despues = $alertas->cambiosPaisImposibles($umbral);
        $ventana = 0.5 + $umbral / 100 * 3.5;
        $etiqueta = sprintf('umbral %d (ventana %.3f h)', $umbral, $ventana);
        assert_igual($usuariosEsperados, $despues['total_changes'] - $antes[$umbral]['total_changes'], "alertas: $etiqueta suma $usuariosEsperados cambio(s) imposible(s)");
        assert_igual($usuariosEsperados, $despues['total_users_affected'] - $antes[$umbral]['total_users_affected'], "alertas: $etiqueta suma $usuariosEsperados usuario(s) afectado(s)");
    }

    // IP fuera del país: las tres acciones que terminan en el país B (z90102, z90202, z90302). La de ip NULL y las
    // que coinciden con el país declarado no cuentan.
    $ipDespues = $alertas->ipMismatches();
    assert_igual(3, $ipDespues['total_mismatches'] - $ipAntes['total_mismatches'], 'alertas: IP fuera del país suma las 3 acciones del país distinto, no la de ip NULL ni las del país declarado');
    assert_igual(3, $ipDespues['total_users_affected'] - $ipAntes['total_users_affected'], 'alertas: IP fuera del país suma los 3 usuarios');

    // El KPI "usuarios con alerta" cuenta lo mismo que las alertas, para cada tipo habilitado y cada umbral.
    foreach ([[true, false], [false, true], [true, true], [false, false]] as [$ipActiva, $cambioActiva]) {
        foreach (array_keys($esperadoPorUmbral) as $umbral) {
            $config->guardar('alerta_ip_pais', $ipActiva ? 'true' : 'false');
            $config->guardar('alerta_cambio_pais', $cambioActiva ? 'true' : 'false');
            $config->guardar('umbral_sensibilidad', (string) $umbral);

            $esperado = ($ipActiva ? $alertas->ipMismatches()['total_users_affected'] : 0)
                + ($cambioActiva ? $alertas->cambiosPaisImposibles($umbral)['total_users_affected'] : 0);
            assert_igual(
                $esperado,
                $kpis(),
                sprintf('kpis: active_alerts_users coincide con las alertas (ip=%s, cambio=%s, umbral=%d)', $ipActiva ? 'sí' : 'no', $cambioActiva ? 'sí' : 'no', $umbral)
            );
        }
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
assert_igual(null, $error, 'alertas-reglas: los datos de prueba se insertan sin error');
assert_igual(0, (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id LIKE 'z9%'")->fetchColumn(), 'alertas-reglas: el rollback no deja usuarios de prueba');
assert_igual(0, (int) $pdo->query("SELECT COUNT(*) FROM acciones WHERE id LIKE 'z9%'")->fetchColumn(), 'alertas-reglas: el rollback no deja acciones de prueba');
