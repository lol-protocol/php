<?php

declare(strict_types=1);

/**
 * Saneamiento de logs de acciones "crudos": formatos de fecha inconsistentes,
 * números como texto, HTML/scripts colados en campos de texto libre, códigos
 * inválidos, campos faltantes. Convierte cada registro crudo al esquema canónico
 * que usa el resto del sistema, o lo descarta si no es recuperable.
 *
 * Se usa una sola vez al generar los datos semilla (ver generar-datos-semilla.php);
 * el backend en vivo ya lee datos limpios, no vuelve a sanear en cada request.
 *
 * Punto de entrada único; la lógica vive modularizada en saneador/*.php.
 */

require __DIR__ . '/saneador/primitivas.php';
require __DIR__ . '/saneador/marca-temporal.php';
require __DIR__ . '/saneador/accion.php';
