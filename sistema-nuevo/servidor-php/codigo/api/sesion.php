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

    $body = api_cuerpo_json();
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
    api_responder([
        'authenticated' => true,
        'username' => $username,
        'csrf_token' => auth_obtener_csrf_token(),
    ]);
}

function api_logout(): void
{
    if (!api_exigir_csrf()) {
        return;
    }

    auth_cerrar_sesion();
    api_responder(['authenticated' => false]);
}

function api_session(): void
{
    api_responder([
        'authenticated' => auth_esta_autenticado(),
        'username' => auth_usuario_actual(),
        'csrf_token' => auth_esta_autenticado() ? auth_obtener_csrf_token() : null,
    ]);
}
