<?php

declare(strict_types=1);

/** Endpoint: /api/notes. Notas libres del admin sobre una acción puntual. */

function api_notas(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'método no permitido']);
        return;
    }

    if (!auth_validar_csrf_header()) {
        http_response_code(403);
        echo json_encode(['error' => 'token CSRF inválido']);
        return;
    }

    $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    $accionId = is_string($body['accion_id'] ?? null) ? $body['accion_id'] : '';
    $texto = is_string($body['texto'] ?? null) ? $body['texto'] : '';

    if ($accionId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'accion_id es requerido']);
        return;
    }

    $almacen = new AlmacenNotas(ConexionBd::obtener());

    // Chequeo explícito en vez de esperar la violación de FK del INSERT: guardar()
    // con texto vacío deriva a un DELETE, que no falla aunque accion_id no exista
    // -- sin este chequeo, la respuesta sería 200 en vez de 404 solo en ese caso.
    if (!$almacen->accionExiste($accionId)) {
        http_response_code(404);
        echo json_encode(['error' => 'acción no encontrada']);
        return;
    }

    $almacen->guardar($accionId, $texto);
    echo json_encode(['ok' => true, 'texto' => trim($texto)]);
}
