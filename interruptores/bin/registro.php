<?php

declare(strict_types=1);

// Muestra los últimos registros del panel, sin abrirlo.
//   php bin/registro.php cambios [n]    cambios de interruptores (por defecto 20)
//   php bin/registro.php accesos [n]    entradas, salidas, bloqueos y caducidades

require_once __DIR__ . '/../src/Interruptores.php';
require_once __DIR__ . '/../src/RegistroJsonl.php';

use LolProtocol\Interruptores\RegistroJsonl;

$var = rtrim((string) (getenv('INTERRUPTORES_VAR') ?: dirname(__DIR__) . '/var'), '/');
$orden = $argv[1] ?? '';
$n = isset($argv[2]) ? (int) $argv[2] : 20;

if (!in_array($orden, ['cambios', 'accesos'], true) || $n <= 0) {
    fwrite(STDERR, "Uso: php bin/registro.php cambios|accesos [n]\n");
    exit(2);
}

$archivo = $orden === 'cambios' ? 'cambios.jsonl' : 'accesos.jsonl';
$eventos = (new RegistroJsonl("$var/$archivo"))->ultimas($n);
if ($eventos === []) {
    echo "No hay registros de {$orden} todavía.\n";
    exit(0);
}

foreach ($eventos as $e) {
    if ($orden === 'cambios') {
        printf("%s  %-16s %-32s %s -> %s\n", $e['ts'] ?? '?', $e['quien'] ?? '?', $e['ref'] ?? '?',
            ($e['antes'] ?? false) ? 'encendido' : 'apagado', ($e['despues'] ?? false) ? 'encendido' : 'apagado');
    } else {
        printf("%s  %-16s %-16s %s\n", $e['ts'] ?? '?', $e['evento'] ?? '?', $e['usuario'] ?? '', $e['origen'] ?? '?');
    }
}
