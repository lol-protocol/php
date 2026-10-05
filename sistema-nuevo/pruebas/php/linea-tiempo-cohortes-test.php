<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/ClienteEstadisticas.php';
require_once __DIR__ . '/../../servidor-php/codigo/api/ayudantes.php';
require_once __DIR__ . '/../../servidor-php/codigo/api/linea-tiempo-cohortes.php';

// api_timeline_con_cohortes() une las dos mitades de cada badge: la cohorte que devuelve el servicio
// de estadísticas y el delta % de la acción contra su promedio. Se prueba contra un servicio falso
// (estadisticas-falsas-router.php) con números conocidos, así que no depende de Java ni de la base,
// y se mira también QUÉ le pidió al servicio: el universo (países, edad, género) y, sobre todo, que
// el usuario que se está mirando quede afuera de su propia comparación.
$log = tempnam(sys_get_temp_dir(), 'estadisticas-falsas-');
$socket = stream_socket_server('tcp://127.0.0.1:0');
$puerto = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);

$servidor = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$puerto", __DIR__ . '/estadisticas-falsas-router.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
    null,
    ['ESTADISTICAS_FALSAS_LOG' => $log, 'PATH' => (string) getenv('PATH')]
);

try {
    for ($i = 0; $i < 50; $i++) {
        $conexion = @fsockopen('127.0.0.1', $puerto, $codigoError, $mensajeError, 0.2);
        if ($conexion) {
            fclose($conexion);
            break;
        }
        usleep(100_000);
    }
    putenv("BACKOFFICE_JAVA_URL=http://127.0.0.1:$puerto");

    $pedidos = function (string $tipo) use ($log): array {
        $resultado = [];
        foreach (file($log, FILE_IGNORE_NEW_LINES) as $linea) {
            parse_str($linea, $query);
            if (($query['type'] ?? null) === $tipo) {
                $resultado[] = $query;
            }
        }
        return $resultado;
    };

    $acciones = [
        ['id' => 'a1', 'type' => 'payment', 'duration_ms' => 1500, 'amount_usd' => 25.0, 'ip_country' => 'AR'],
        ['id' => 'a2', 'type' => 'payment', 'duration_ms' => 500, 'amount_usd' => null, 'ip_country' => 'US'],
        ['id' => 'a3', 'type' => 'login', 'duration_ms' => 200, 'amount_usd' => null, 'ip_country' => null],
        ['id' => 'a4', 'type' => 'vacio', 'duration_ms' => 300, 'amount_usd' => 5.0, 'ip_country' => 'AR'],
        ['id' => 'a5', 'type' => 'roto', 'duration_ms' => 300, 'amount_usd' => null, 'ip_country' => 'AR'],
    ];
    $resultado = api_timeline_con_cohortes($acciones, 'u007', 'AR', ['AR', 'BR'], 20, 50, 'F');
    [$a1, $a2, $a3, $a4, $a5] = $resultado['timeline'];

    assert_igual(10, $a1['cohort']['count'], 'cohortes: la acción trae la cohorte de SU tipo (payment)');
    assert_igual(50.0, $a1['duration_delta_pct'], 'cohortes: duración 1500 contra un promedio de 1000 -> +50%');
    assert_igual(-50.0, $a1['amount_delta_pct'], 'cohortes: monto 25 contra un promedio de 50 -> -50%');
    assert_igual(false, $a1['ip_mismatch'], 'cohortes: IP del mismo país que el usuario -> sin ip_mismatch');

    assert_igual(-50.0, $a2['duration_delta_pct'], 'cohortes: dos acciones del mismo tipo se comparan contra la misma cohorte');
    assert_igual(null, $a2['amount_delta_pct'], 'cohortes: una acción sin monto no tiene delta de monto');
    assert_igual(true, $a2['ip_mismatch'], 'cohortes: IP de otro país que el declarado -> ip_mismatch');

    assert_igual(0.0, $a3['duration_delta_pct'], 'cohortes: igual al promedio de su tipo (login) -> 0%');
    assert_igual(null, $a3['amount_delta_pct'], 'cohortes: login no tiene monto -> sin delta');
    assert_igual(false, $a3['ip_mismatch'], 'cohortes: sin país de IP no hay ip_mismatch');

    assert_igual(0, $a4['cohort']['count'], 'cohortes: el servicio informa un universo vacío');
    assert_igual(null, $a4['duration_delta_pct'], 'cohortes: universo vacío (promedio 0.0) -> sin delta de duración, no división por cero');
    assert_igual(null, $a4['amount_delta_pct'], 'cohortes: universo vacío (promedio de monto null) -> sin delta de monto');

    assert_igual(null, $a5['cohort'], 'cohortes: si el servicio falla para un tipo, su cohorte es null');
    assert_igual(null, $a5['duration_delta_pct'], 'cohortes: sin cohorte no hay delta de duración');
    assert_igual(null, $a5['amount_delta_pct'], 'cohortes: sin cohorte no hay delta de monto');
    assert_igual(false, $resultado['stats_service_available'], 'cohortes: un tipo sin respuesta marca el servicio como no disponible');

    $pagos = $pedidos('payment');
    assert_igual(1, count($pagos), 'cohortes: se pide una sola vez por tipo, aunque haya varias acciones de ese tipo');
    assert_igual('u007', $pagos[0]['exclude'] ?? null, 'cohortes: el usuario que se mira queda afuera de su propia comparación (exclude)');
    assert_igual('AR,BR', $pagos[0]['countries'] ?? null, 'cohortes: el universo de países llega al servicio');
    assert_igual(['20', '50', 'F'], [$pagos[0]['age_min'] ?? null, $pagos[0]['age_max'] ?? null, $pagos[0]['gender'] ?? null], 'cohortes: edad y género llegan al servicio');

    // Sin filtro de país (scope "all"): no se manda "countries" y todo responde -> servicio disponible.
    file_put_contents($log, '');
    $todos = api_timeline_con_cohortes(array_slice($acciones, 0, 3), 'u007', 'AR', null, 0, 150, 'all');
    assert_igual(true, $todos['stats_service_available'], 'cohortes: todos los tipos con respuesta -> servicio disponible');
    $pagosTodos = $pedidos('payment');
    assert_verdadero(!array_key_exists('countries', $pagosTodos[0] ?? ['countries' => 1]), 'cohortes: sin filtro de país no se manda "countries"');
    assert_igual('u007', $pagosTodos[0]['exclude'] ?? null, 'cohortes: también sin filtro de país se excluye al propio usuario');
} finally {
    proc_terminate($servidor);
    proc_close($servidor);
    unlink($log);
    putenv('BACKOFFICE_JAVA_URL');
}
