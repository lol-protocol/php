<?php

declare(strict_types=1);

/** Endpoint: /api/notes. Notas libres del admin sobre una acción puntual. */

/** Largo máximo de una nota, en caracteres. La interfaz pone el mismo tope en el textarea (nota-bloque.js). */
const NOTA_MAX_CARACTERES = 5000;

function nota_demasiado_larga(string $texto): bool
{
    return mb_strlen(trim($texto)) > NOTA_MAX_CARACTERES;
}

function api_notas(): void
{
    if (!api_exigir_metodo('POST') || !api_exigir_csrf()) {
        return;
    }

    $body = api_cuerpo_json();
    $accionId = is_string($body['accion_id'] ?? null) ? $body['accion_id'] : '';
    $texto = is_string($body['texto'] ?? null) ? $body['texto'] : '';

    if ($accionId === '') {
        api_error(400, 'accion_id_requerido', 'accion_id es requerido');
        return;
    }
    if (nota_demasiado_larga($texto)) {
        api_error(400, 'nota_demasiado_larga', 'la nota supera ' . NOTA_MAX_CARACTERES . ' caracteres');
        return;
    }

    $almacen = new AlmacenNotas(ConexionBd::obtener());

    // Chequeo explícito en vez de esperar la violación de FK del INSERT: guardar()
    // con texto vacío deriva a un DELETE, que no falla aunque accion_id no exista
    // -- sin este chequeo, la respuesta sería 200 en vez de 404 solo en ese caso.
    if (!$almacen->accionExiste($accionId)) {
        api_error(404, 'accion_no_encontrada', 'acción no encontrada');
        return;
    }

    $almacen->guardar($accionId, $texto);
    api_responder(['ok' => true, 'texto' => trim($texto)]);
}
