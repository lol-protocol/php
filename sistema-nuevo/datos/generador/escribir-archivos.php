<?php

declare(strict_types=1);

/** Escritura de los archivos de salida (JSON legible + CSV para el servicio Java). */

/**
 * Escribe a un temporal en el mismo directorio y hace rename() al final: en
 * sistemas POSIX rename() es atómico, así que cualquier lector que abra $ruta
 * en cualquier momento ve o el archivo viejo completo, o el nuevo completo,
 * nunca un estado a medio escribir. Esto importa en particular para el CSV:
 * el servicio Java lo recarga solo cuando detecta un cambio de mtime (ver
 * CargadorAcciones.java), y sin esto podía leerlo vacío o truncado a mitad
 * de la escritura.
 */
function escribir_json(string $ruta, $datos): void
{
    $tmp = $ruta . '.tmp';
    file_put_contents($tmp, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmp, $ruta);
}

function escribir_csv_acciones(string $ruta, array $acciones, array $usersById): void
{
    $tmp = $ruta . '.tmp';
    $csv = fopen($tmp, 'w');
    fputcsv($csv, ['user_id', 'type', 'duration_ms', 'amount_usd', 'country', 'age', 'gender', 'timestamp']);
    foreach ($acciones as $a) {
        $u = $usersById[$a['user_id']];
        fputcsv($csv, [
            $a['user_id'], $a['type'], $a['duration_ms'], $a['amount_usd'] ?? '',
            $u['country'], $u['age'], $u['gender'], $a['timestamp'],
        ]);
    }
    fclose($csv);
    rename($tmp, $ruta);
}
