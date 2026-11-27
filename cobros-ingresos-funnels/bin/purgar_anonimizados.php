<?php

declare(strict_types=1);

/**
 * Borra los datos originales de los clientes anonimizados cuyo plazo para deshacer ya vencio (migracion 022): desde ahi la anonimizacion
 * es definitiva. Pensado para el cron, una vez al dia:
 *
 *     17 3 * * *  cd /ruta/cobros-ingresos-funnels && php bin/purgar_anonimizados.php
 *
 * No hace nada si no hay nada vencido. Los respaldos hechos antes de anonimizar siguen teniendo los datos: depuralos segun tu retencion.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repositories\AnonimizacionRepository;

$cuantos = (new AnonimizacionRepository())->purgarVencidos();
echo $cuantos === 0
    ? "Nada que purgar.\n"
    : sprintf("Purgados los datos originales de %d cliente%s anonimizado%s.\n", $cuantos, $cuantos === 1 ? '' : 's', $cuantos === 1 ? '' : 's');
