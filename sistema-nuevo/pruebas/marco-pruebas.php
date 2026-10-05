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
