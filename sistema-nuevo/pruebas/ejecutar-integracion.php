<?php

declare(strict_types=1);

/**
 * Corre las pruebas de integración (pruebas/php-integracion/*-test.php) contra
 * PostgreSQL real -- a diferencia de ejecutar-php.php (puro, sin BD), estas
 * prueban los Almacen*.php que son wrappers delgados sobre SQL.
 * Requiere PostgreSQL arriba con los datos semilla ya cargados.
 * Uso: php pruebas/ejecutar-integracion.php
 */

require __DIR__ . '/marco-pruebas.php';
require __DIR__ . '/../servidor-php/codigo/ConexionBd.php';
require __DIR__ . '/../servidor-php/codigo/AlmacenFiltros.php';
require __DIR__ . '/../servidor-php/codigo/AlmacenNotas.php';
require __DIR__ . '/../servidor-php/codigo/AlmacenConfiguracion.php';
require __DIR__ . '/../servidor-php/codigo/AlmacenIntentosLogin.php';
require __DIR__ . '/../servidor-php/codigo/AlmacenKpis.php';
require __DIR__ . '/../servidor-php/codigo/AlmacenAlertas.php';

$pdo = ConexionBd::obtener();

foreach (glob(__DIR__ . '/php-integracion/*-test.php') as $archivo) {
    require $archivo;
}

exit(pruebas_resumen());
