<?php

declare(strict_types=1);

/** Escritura de los archivos de salida (JSON legible + CSV para el servicio Java). */

function escribir_json(string $ruta, $datos): void
{
    file_put_contents($ruta, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function escribir_csv_acciones(string $ruta, array $acciones, array $usersById): void
{
    $csv = fopen($ruta, 'w');
    fputcsv($csv, ['user_id', 'type', 'duration_ms', 'amount_usd', 'country', 'age', 'gender', 'timestamp']);
    foreach ($acciones as $a) {
        $u = $usersById[$a['user_id']];
        fputcsv($csv, [
            $a['user_id'], $a['type'], $a['duration_ms'], $a['amount_usd'] ?? '',
            $u['country'], $u['age'], $u['gender'], $a['timestamp'],
        ]);
    }
    fclose($csv);
}
