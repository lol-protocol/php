<?php

declare(strict_types=1);

/** Endpoints de sesión: /api/login, /api/logout, /api/session. */

function api_login(): void
{
    $pdo = ConexionBd::obtener();
    $intentos = new AlmacenIntentosLogin($pdo);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';

    $bloqueadaHasta = $intentos->bloqueadaHasta($ip);
    if ($bloqueadaHasta !== null) {
        api_error(429, 'demasiados_intentos', 'demasiados intentos fallidos, probá de nuevo más tarde', ['retry_after' => $bloqueadaHasta]);
        return;
    }

    $body = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
    $username = is_string($body['username'] ?? null) ? $body['username'] : '';
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';

    if ($username === '' || $password === '' || !auth_verificar_credenciales($pdo, $username, $password)) {
        $bloqueadaAhora = $intentos->registrarFallo($ip);
        if ($bloqueadaAhora !== null) {
            api_error(429, 'demasiados_intentos', 'demasiados intentos fallidos, probá de nuevo más tarde', ['retry_after' => $bloqueadaAhora]);
            return;
        }
        api_error(401, 'credenciales_invalidas', 'usuario o contraseña incorrectos');
        return;
    }

    $intentos->limpiar($ip);
    auth_marcar_autenticado($username);
    echo json_encode([
        'authenticated' => true,
        'username' => $username,
        'csrf_token' => auth_obtener_csrf_token(),
    ], JSON_UNESCAPED_UNICODE);
}

function api_logout(): void
{
    if (!auth_validar_csrf_header()) {
        api_error(403, 'csrf_invalido', 'token CSRF inválido');
        return;
    }

    auth_cerrar_sesion();
    echo json_encode(['authenticated' => false], JSON_UNESCAPED_UNICODE);
}

function api_session(): void
{
    echo json_encode([
        'authenticated' => auth_esta_autenticado(),
        'username' => auth_usuario_actual(),
        'csrf_token' => auth_esta_autenticado() ? auth_obtener_csrf_token() : null,
    ], JSON_UNESCAPED_UNICODE);
}
