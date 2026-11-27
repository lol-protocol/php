<?php

declare(strict_types=1);

// Cambios programados de interruptores (las horas son UTC).
//   php bin/programados.php ejecutar                                  aplica lo vencido: va en un cron, cada minuto
//   php bin/programados.php listar
//   php bin/programados.php agendar <fecha UTC> escena <id>           p. ej. 2026-12-01T03:00:00Z escena mantenimiento
//   php bin/programados.php agendar <fecha UTC> ref <ref> <on|off>    p. ej. 2026-12-01T03:00:00Z ref gen.pagos off
//   php bin/programados.php cancelar <id>
//
// Cron de ejemplo:  * * * * * cd /ruta/interruptores && php bin/programados.php ejecutar >> var/programados.log 2>&1

require_once __DIR__ . '/../src/Interruptores.php';
require_once __DIR__ . '/../src/Escenas.php';
require_once __DIR__ . '/../src/Programados.php';

use LolProtocol\Interruptores\Escenas;
use LolProtocol\Interruptores\Interruptores;
use LolProtocol\Interruptores\Programados;

$raiz = dirname(__DIR__);
$var = rtrim((string) (getenv('INTERRUPTORES_VAR') ?: $raiz . '/var'), '/');

function uso(): never
{
    fwrite(STDERR, "Uso: php bin/programados.php ejecutar | listar | agendar <fecha UTC> escena <id> | agendar <fecha UTC> ref <ref> <on|off> | cancelar <id>\n");
    exit(2);
}

try {
    $int = new Interruptores($raiz . '/catalogo.json', $var . '/estado.json');
    $escenas = new Escenas($raiz . '/escenas.json', $int, $var . '/ultima-escena.json');
    $prog = new Programados($var . '/programados.json');
    $orden = $argv[1] ?? '';
    $args = array_slice($argv, 2);

    switch ($orden) {
        case 'ejecutar':
            $hechos = $prog->ejecutarVencidos($int, $escenas);
            foreach ($hechos as $h) {
                echo gmdate('Y-m-d\TH:i:s\Z') . "  {$h['id']}  {$h['estado']}  {$h['resultado']}\n";
            }
            exit(in_array('error', array_column($hechos, 'estado'), true) ? 1 : 0);

        case 'listar':
            $lista = $prog->listar();
            if ($lista === []) {
                echo "No hay cambios programados.\n";
            }
            foreach ($lista as $p) {
                $que = isset($p['accion']['escena']) ? 'escena ' . $p['accion']['escena'] : $p['accion']['ref'] . ' → ' . ($p['accion']['valor'] ? 'on' : 'off');
                printf("%s  %s  %-10s %-34s %s %s\n", $p['id'], gmdate('Y-m-d\TH:i:s\Z', (int) $p['cuando']), $p['estado'], $que, $p['creado_por'], $p['resultado']);
            }
            break;

        case 'agendar':
            $cuando = strtotime((string) ($args[0] ?? ''));
            if ($cuando === false || count($args) < 3) {
                uso();
            }
            if ($args[1] === 'escena' && count($args) === 3) {
                $escenas->cambios($args[2]); // falla si la escena no existe
                $accion = ['escena' => $args[2]];
            } elseif ($args[1] === 'ref' && count($args) === 4 && in_array($args[3], ['on', 'off'], true)) {
                $int->activo($args[2]); // falla si la ref no existe
                $accion = ['ref' => $args[2], 'valor' => $args[3] === 'on'];
            } else {
                uso();
            }
            echo 'Programado ' . $prog->agendar($cuando, $accion, 'terminal') . ' para ' . gmdate('Y-m-d\TH:i:s\Z', $cuando) . ".\n";
            break;

        case 'cancelar':
            if (count($args) !== 1) {
                uso();
            }
            $prog->cancelar($args[0]);
            echo "Cancelado {$args[0]}.\n";
            break;

        default:
            uso();
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
