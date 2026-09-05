<?php

declare(strict_types=1);

/** Endpoints de sesión: /api/login, /api/logout, /api/session. */

function api_login(): void
{
    $body = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
    $username = is_string($body['username'] ?? null) ? $body['username'] : '';
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';

    if ($username === '' || $password === '' || !auth_verificar_credenciales($username, $password)) {
        http_response_code(401);
        echo json_encode(['error' => 'usuario o contraseña incorrectos']);
        return;
    }

    auth_marcar_autenticado($username);
    echo json_encode([
        'authenticated' => true,
        'username' => $username,
        'csrf_token' => auth_obtener_csrf_token(),
    ]);
}

function api_logout(): void
{
    $body = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
    $token = is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '';

    if (!auth_validar_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'token CSRF inválido']);
        return;
    }

    auth_cerrar_sesion();
    echo json_encode(['authenticated' => false]);
}

function api_session(): void
{
    echo json_encode([
        'authenticated' => auth_esta_autenticado(),
        'username' => auth_usuario_actual(),
        'csrf_token' => auth_esta_autenticado() ? auth_obtener_csrf_token() : null,
    ]);
}
