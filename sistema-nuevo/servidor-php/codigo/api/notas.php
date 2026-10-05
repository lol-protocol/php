<?php

declare(strict_types=1);

/** Endpoint: /api/notes. Notas libres del admin sobre una acción puntual. */

function api_notas(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error(405, 'metodo_no_permitido', 'método no permitido');
        return;
    }

    if (!auth_validar_csrf_header()) {
        api_error(403, 'csrf_invalido', 'token CSRF inválido');
        return;
    }

    $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    $accionId = is_string($body['accion_id'] ?? null) ? $body['accion_id'] : '';
    $texto = is_string($body['texto'] ?? null) ? $body['texto'] : '';

    if ($accionId === '') {
        api_error(400, 'accion_id_requerido', 'accion_id es requerido');
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
    echo json_encode(['ok' => true, 'texto' => trim($texto)], JSON_UNESCAPED_UNICODE);
}
