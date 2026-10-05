<?php

declare(strict_types=1);

/**
 * Manejadores de los endpoints /api/*. Punto de entrada único; la lógica vive
 * modularizada en api/*.php y la tabla de rutas (qué función atiende cada una y
 * cuáles exigen sesión) en rutas.php.
 */

require_once __DIR__ . '/api/ayudantes.php';
require_once __DIR__ . '/api/sesion.php';
require_once __DIR__ . '/api/usuarios.php';
require_once __DIR__ . '/api/alertas.php';
require_once __DIR__ . '/api/alertas-config.php';
require_once __DIR__ . '/api/filtros.php';
require_once __DIR__ . '/api/notas.php';
require_once __DIR__ . '/api/kpis.php';
require_once __DIR__ . '/api/linea-tiempo-cohortes.php';
require_once __DIR__ . '/api/linea-tiempo.php';
require_once __DIR__ . '/rutas.php';
