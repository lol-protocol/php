<?php

declare(strict_types=1);

/**
 * Manejadores de los endpoints /api/*. Punto de entrada único; la lógica vive
 * modularizada en api/*.php.
 */

require __DIR__ . '/api/ayudantes.php';
require __DIR__ . '/api/sesion.php';
require __DIR__ . '/api/usuarios.php';
require __DIR__ . '/api/alertas.php';
require __DIR__ . '/api/alertas-config.php';
require __DIR__ . '/api/linea-tiempo-cohortes.php';
require __DIR__ . '/api/linea-tiempo.php';
