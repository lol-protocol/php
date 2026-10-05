<?php

declare(strict_types=1);

/**
 * Siembra la base solo si todavía no está sembrada (criterio en generador/base-sembrada.php).
 * Es lo que corre ejecutar.sh en cada arranque. generar-datos-semilla.php, en cambio, recrea
 * todo desde cero y por eso borra también lo que el usuario guardó desde el panel.
 *
 * Uso: php sembrar-si-falta.php
 */

require __DIR__ . '/../servidor-php/codigo/ConexionBd.php';
require __DIR__ . '/generador/base-sembrada.php';

if (hay_que_sembrar(ConexionBd::obtener(), __DIR__)) {
    require __DIR__ . '/generar-datos-semilla.php';
}
