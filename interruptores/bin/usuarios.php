<?php

declare(strict_types=1);

// Gestiona los usuarios del panel.
//   php bin/usuarios.php listar
//   php bin/usuarios.php crear <nombre> <lector|editor>    (pide la clave; no se ve al escribir)
//   php bin/usuarios.php rol <nombre> <lector|editor>
//   php bin/usuarios.php clave <nombre>                    (pide la clave nueva)
//   php bin/usuarios.php borrar <nombre>
//
// La clave se lee de la terminal sin eco. Con tubería (p. ej. desde un gestor de secretos):
//   printf '%s' "$CLAVE" | php bin/usuarios.php crear ana editor
// Nunca se imprime una clave ni un hash.

require_once __DIR__ . '/../src/Usuarios.php';

use LolProtocol\Interruptores\Usuarios;

$ruta = (getenv('INTERRUPTORES_VAR') ?: dirname(__DIR__) . '/var') . '/usuarios.json';
$u = new Usuarios($ruta);

function uso(): never
{
    fwrite(STDERR, "Uso: php bin/usuarios.php listar | crear <nombre> <rol> | rol <nombre> <rol> | clave <nombre> | borrar <nombre>\n");
    exit(2);
}

/** Lee una línea de la entrada; en una terminal, sin mostrar lo que se escribe. */
function leerClave(string $pregunta): string
{
    fwrite(STDERR, $pregunta);
    $tty = function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($tty) {
        shell_exec('stty -echo');
    }
    $linea = fgets(STDIN);
    if ($tty) {
        shell_exec('stty echo');
        fwrite(STDERR, "\n");
    }
    return rtrim((string) $linea, "\r\n");
}

$orden = $argv[1] ?? '';
$args = array_slice($argv, 2);

try {
    switch ($orden) {
        case 'listar':
            $lista = $u->listar();
            if ($lista === []) {
                echo "No hay usuarios. Crea uno con: php bin/usuarios.php crear <nombre> editor\n";
                break;
            }
            foreach ($lista as $nombre => $datos) {
                echo sprintf("%-32s %-8s creado %s\n", $nombre, $datos['rol'], $datos['creado']);
            }
            break;

        case 'crear':
            if (count($args) !== 2) {
                uso();
            }
            $u->crear($args[0], $args[1], leerClave('Clave nueva: '));
            echo "Creado «{$args[0]}» ({$args[1]}).\n";
            break;

        case 'rol':
            if (count($args) !== 2) {
                uso();
            }
            $u->cambiarRol($args[0], $args[1]);
            echo "«{$args[0]}» ahora es {$args[1]}.\n";
            break;

        case 'clave':
            if (count($args) !== 1) {
                uso();
            }
            $u->cambiarClave($args[0], leerClave('Clave nueva: '));
            echo "Clave cambiada para «{$args[0]}».\n";
            break;

        case 'borrar':
            if (count($args) !== 1) {
                uso();
            }
            $u->borrar($args[0]);
            echo "Borrado «{$args[0]}».\n";
            break;

        default:
            uso();
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
