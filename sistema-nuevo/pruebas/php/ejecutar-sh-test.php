<?php

declare(strict_types=1);

// ejecutar.sh levanta 3 servidores y se queda esperándolos, así que no se puede correr "de
// verdad" dentro de una prueba. Se corre una copia en un directorio temporal con php, java y
// javac falsos (solo anotan cómo los llamaron) y un preparar-postgres.sh que no hace nada, y
// se mira qué siembra pidió. Lo que se vigila: arrancar NO puede pedir la siembra destructiva
// (generar-datos-semilla.php hace DROP de todas las tablas, también las que el usuario escribe
// desde el panel: notas, filtros guardados, config de alertas) -- esa es solo de --regenerar.
$raiz = realpath(__DIR__ . '/../..');

/** @return array{salida: string, codigo: int, llamadas: list<string>} */
function correr_ejecutar_sh(string $raiz, array $args): array
{
    $tmp = sys_get_temp_dir() . '/ejecutar-sh-prueba-' . bin2hex(random_bytes(4));
    mkdir("$tmp/bin", 0755, true);
    mkdir("$tmp/servicio-estadisticas-java");
    copy("$raiz/ejecutar.sh", "$tmp/ejecutar.sh");

    $falsos = ['preparar-postgres.sh' => "$tmp/preparar-postgres.sh"];
    foreach (['php', 'java', 'javac'] as $comando) {
        $falsos[$comando] = "$tmp/bin/$comando";
    }
    foreach ($falsos as $nombre => $ruta) {
        $anotar = $nombre === 'preparar-postgres.sh' ? $nombre : "$nombre \$*";
        file_put_contents($ruta, "#!/usr/bin/env bash\necho \"$anotar\" >> \"\$LLAMADAS\"\n");
        chmod($ruta, 0755);
    }

    $corrida = ejecutar_proceso(
        array_merge(['timeout', '30', 'bash', "$tmp/ejecutar.sh"], $args),
        $tmp,
        ['PATH' => "$tmp/bin:" . getenv('PATH'), 'LLAMADAS' => "$tmp/llamadas.log"]
    );

    $llamadas = is_file("$tmp/llamadas.log") ? file("$tmp/llamadas.log", FILE_IGNORE_NEW_LINES) : [];
    exec('rm -rf ' . escapeshellarg($tmp));

    return $corrida + ['llamadas' => $llamadas];
}

$siembras = fn (array $r): array => array_values(array_filter($r['llamadas'], fn ($l) => str_starts_with($l, 'php datos/')));

$normal = correr_ejecutar_sh($raiz, []);
assert_igual(0, $normal['codigo'], 'ejecutar.sh: sin opciones arranca (código 0)');
assert_igual(
    ['php datos/sembrar-si-falta.php'],
    $siembras($normal),
    'ejecutar.sh: sin opciones solo siembra si falta, no recrea la base (y borra notas, filtros y config) en cada arranque'
);
assert_igual('preparar-postgres.sh', $normal['llamadas'][0] ?? null, 'ejecutar.sh: deja PostgreSQL listo antes de sembrar');
assert_verdadero(in_array('java ServicioEstadisticas', $normal['llamadas'], true), 'ejecutar.sh: levanta el servicio Java');
$servidores = array_values(array_filter($normal['llamadas'], fn ($l) => str_contains($l, '-S localhost:')));
assert_igual(2, count($servidores), 'ejecutar.sh: levanta la API y el panel');

$regenerar = correr_ejecutar_sh($raiz, ['--regenerar']);
assert_igual(0, $regenerar['codigo'], 'ejecutar.sh --regenerar: arranca (código 0)');
assert_igual(
    ['php datos/generar-datos-semilla.php'],
    $siembras($regenerar),
    'ejecutar.sh --regenerar: recrea los datos desde cero (generar-datos-semilla.php)'
);
assert_verdadero(str_contains($regenerar['salida'], 'se borran'), 'ejecutar.sh --regenerar: avisa que borra notas, filtros guardados y config');

$invalida = correr_ejecutar_sh($raiz, ['--borrar-todo']);
assert_igual(2, $invalida['codigo'], 'ejecutar.sh: una opción desconocida es un error de uso (código 2), no se ignora');
assert_igual([], $invalida['llamadas'], 'ejecutar.sh: una opción desconocida no hace nada (ni prepara la base ni levanta servicios)');
