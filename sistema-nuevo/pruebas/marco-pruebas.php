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
