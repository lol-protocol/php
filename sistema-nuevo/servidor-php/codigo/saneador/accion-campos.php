<?php

declare(strict_types=1);

/**
 * Sanea los campos específicos de cada tipo de acción (comentario, endpoint +
 * código HTTP, tamaño de archivo) y los escribe en $limpio.
 */
function saneador_aplicar_campos_tipo(array &$limpio, array $crudo, string $tipo): void
{
    if ($tipo === 'review_submit' || $tipo === 'support_ticket') {
        $limpio['comment'] = saneador_texto($crudo['comment'] ?? null, 300);
    }

    if ($tipo === 'api_call') {
        $limpio['endpoint'] = saneador_texto($crudo['endpoint'] ?? null, 120);
        $limpio['http_status'] = saneador_codigo_http($crudo['http_status'] ?? null);
    }

    if (isset($crudo['file_size_kb'])) {
        $tamano = saneador_numero($crudo['file_size_kb']);
        $limpio['file_size_kb'] = ($tamano !== null && $tamano > 0) ? round($tamano, 1) : null;
    }
}
