<?php

declare(strict_types=1);

/** Framework de pruebas mínimo, sin dependencias (no hay phpunit instalado acá). */

$GLOBALS['__pruebas_total'] = 0;
$GLOBALS['__pruebas_fallidas'] = [];

function assert_igual(mixed $esperado, mixed $real, string $mensaje): void
{
    $GLOBALS['__pruebas_total']++;
    if ($esperado === $real) {
        return;
    }
    $GLOBALS['__pruebas_fallidas'][] = sprintf(
        "%s\n    esperado: %s\n    real:     %s",
        $mensaje,
        var_export($esperado, true),
        var_export($real, true)
    );
}

function assert_verdadero(bool $condicion, string $mensaje): void
{
    assert_igual(true, $condicion, $mensaje);
}

/**
 * Código fuente de todos los .php bajo $directorio, por ruta relativa. Para las pruebas que vigilan que un
 * patrón (un json_encode suelto, un 'error' => armado a mano...) no vuelva a aparecer fuera de su helper.
 *
 * @return array<string,string>
 */
function fuentes_php(string $directorio): array
{
    $fuentes = [];
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS));
    foreach ($archivos as $archivo) {
        if ($archivo->getExtension() === 'php') {
            $fuentes[substr($archivo->getPathname(), strlen($directorio) + 1)] = (string) file_get_contents($archivo->getPathname());
        }
    }
    ksort($fuentes);
    return $fuentes;
}

/** El código fuente sin el cuerpo de una función de primer nivel (que cierra con "}" en la columna 0). */
function sin_funcion(string $fuente, string $nombre): string
{
    return (string) preg_replace('/function ' . preg_quote($nombre, '/') . '\(.*?\n}\n/s', '', $fuente);
}

/**
 * Rutas de los archivos (de $fuentes) donde $patron aparece, descontando el cuerpo de las funciones $helpers:
 * el único lugar donde está permitido.
 *
 * @param array<string,string> $fuentes
 * @param string[] $helpers
 * @return string[]
 */
function archivos_con_patron(array $fuentes, string $patron, array $helpers = []): array
{
    $encontrados = [];
    foreach ($fuentes as $ruta => $fuente) {
        foreach ($helpers as $helper) {
            $fuente = sin_funcion($fuente, $helper);
        }
        if (preg_match($patron, $fuente) === 1) {
            $encontrados[] = $ruta;
        }
    }
    return $encontrados;
}

/**
 * Los textos entre comillas simples o dobles de un código fuente PHP, sin sus comillas y sin mirar los comentarios:
 * para vigilar que un literal (una ruta, un SQL...) no aparezca fuera del archivo donde debe vivir.
 *
 * @return string[]
 */
function literales_de_texto(string $fuente): array
{
    $literales = [];
    foreach (token_get_all($fuente) as $token) {
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $literales[] = substr($token[1], 1, -1);
        }
    }
    return $literales;
}

/**
 * Corre un comando, espera a que termine y devuelve lo que escribió (la salida y la de errores, juntas) y su código de
 * salida. Con $entorno null corre con el del proceso actual.
 *
 * @param string[] $comando
 * @param array<string,string>|null $entorno
 * @return array{salida: string, codigo: int}
 */
function ejecutar_proceso(array $comando, ?string $directorio = null, ?array $entorno = null): array
{
    $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directorio, $entorno);
    $salida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return ['salida' => $salida, 'codigo' => proc_close($proceso)];
}

/** El puerto de un socket abierto con "tcp://127.0.0.1:0": el sistema operativo elige uno libre. */
function puerto_de($socket): int
{
    return (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
}

/** Un puerto de 127.0.0.1 que nadie usa: se abre un socket, se anota el puerto y se cierra. */
function puerto_libre(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $puerto = puerto_de($socket);
    fclose($socket);
    return $puerto;
}

/**
 * Un servidor que acepta conexiones y nunca responde: peor que uno caído, porque cada pedido espera su timeout entero.
 * Al terminar hay que cerrar el socket con fclose().
 *
 * @return array{0: resource, 1: int} El socket y su puerto.
 */
function servidor_colgado(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    return [$socket, puerto_de($socket)];
}

/**
 * Levanta "php -S" en un puerto libre con $router y espera a que acepte conexiones (si no lo hace, falla). $entorno son las
 * variables que le llegan al router, además del PATH. Se termina con detener_servidor_php().
 *
 * @param array<string,string> $entorno
 * @return array{proceso: resource, puerto: int}
 */
function levantar_servidor_php(string $router, array $entorno = []): array
{
    $puerto = puerto_libre();
    $proceso = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:$puerto", $router],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
        null,
        $entorno + ['PATH' => (string) getenv('PATH')]
    );
    for ($intento = 0; $intento < 50; $intento++) {
        $conexion = @fsockopen('127.0.0.1', $puerto, $codigoError, $mensajeError, 0.2);
        if ($conexion) {
            fclose($conexion);
            return ['proceso' => $proceso, 'puerto' => $puerto];
        }
        usleep(100_000);
    }
    proc_terminate($proceso);
    proc_close($proceso);
    throw new RuntimeException("El servidor de prueba ($router) no levantó en el puerto $puerto");
}

/** @param array{proceso: resource, puerto: int} $servidor */
function detener_servidor_php(array $servidor): void
{
    proc_terminate($servidor['proceso']);
    proc_close($servidor['proceso']);
}

/** @return int Código de salida: 0 si todo pasó, 1 si hubo fallas (para CI). */
function pruebas_resumen(): int
{
    $total = $GLOBALS['__pruebas_total'];
    $fallidas = $GLOBALS['__pruebas_fallidas'];

    foreach ($fallidas as $f) {
        fwrite(STDERR, "✗ FALLÓ: $f\n\n");
    }

    $ok = $total - count($fallidas);
    fwrite(STDERR, sprintf("%d/%d pruebas OK\n", $ok, $total));

    return $fallidas === [] ? 0 : 1;
}
