<?php

declare(strict_types=1);

/**
 * Comprueba, desde afuera, que el panel no responde a quien llega sin credenciales.
 *
 * El panel no tiene login: lo que lo protege es el servidor web (autenticacion basica, una
 * lista de IPs, una VPN). Esto pide varias pantallas sin mandar ninguna credencial y
 * dice si alguna contesta (ver App\Acceso\ComprobadorDeAcceso). Hacerlo despues de
 * configurar el servidor, y de cada cambio en su configuracion.
 *
 * Uso:
 *   php bin/comprobar_acceso.php https://panel.ejemplo.com
 *   php bin/comprobar_acceso.php --ayuda
 *
 * Termina con 0 si todas las pantallas piden credenciales (401 o 403), con 1 si alguna
 * contesta (el panel esta abierto) y con 2 si no se puede afirmar nada (una redireccion, un
 * 404, que no se pueda conectar) o si los argumentos estan mal. No necesita la base de datos.
 *
 * Si el acceso se limita por IP, correrlo desde una direccion que NO este permitida.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Acceso\ComprobadorDeAcceso;

const USO = <<<'TEXTO'
Uso:
  php bin/comprobar_acceso.php https://panel.ejemplo.com
  php bin/comprobar_acceso.php --ayuda

TEXTO;

$argumentos = array_slice($argv, 1);
if (in_array('--ayuda', $argumentos, true)) {
    echo USO;
    exit(0);
}
if (count($argumentos) !== 1 || str_starts_with($argumentos[0], '--')) {
    fwrite(STDERR, "Hay que pasar la dirección del panel, y nada más.\n\n" . USO);
    exit(2);
}

try {
    $informe = (new ComprobadorDeAcceso())->comprobar($argumentos[0]);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n\n" . USO);
    exit(2);
}

printf("Comprobando %s sin enviar credenciales...\n", ComprobadorDeAcceso::normalizar($argumentos[0]));
foreach ($informe['pantallas'] as $pantalla) {
    printf(
        "  %-10s %-4s %s%s\n",
        $pantalla['veredicto'],
        $pantalla['estado'] ?? '-',
        $pantalla['ruta'],
        $pantalla['detalle'] !== null ? '  (' . $pantalla['detalle'] . ')' : ''
    );
}

switch ($informe['resultado']) {
    case ComprobadorDeAcceso::PROTEGIDO:
        echo "\nPROTEGIDO: todas las pantallas piden credenciales.\n";
        exit(0);
    case ComprobadorDeAcceso::ABIERTO:
        fwrite(STDERR, "\nABIERTO: alguna pantalla contesta sin pedir credenciales. Cualquiera con la dirección puede ver y modificar los datos.\n"
            . "Revisá la autenticación básica o la lista de IPs del servidor web (ver el README, «Antes de exponerlo»).\n"
            . "Si el acceso se limita por IP, esto tiene que correr desde una dirección que no esté permitida.\n");
        exit(1);
    default:
        fwrite(STDERR, "\nINCONCLUSO: alguna pantalla no contestó ni con 401/403 ni con 2xx, así que no se puede afirmar que esté protegida.\n"
            . "Si redirige, probá con la dirección a la que redirige (casi siempre la https).\n");
        exit(2);
}
