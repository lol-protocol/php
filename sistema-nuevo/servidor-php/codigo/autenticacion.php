<?php

declare(strict_types=1);

/**
 * Autenticación simple por sesión (un único usuario admin, sin roles ni registro).
 *
 * Con protección CSRF: token regenerado en cada login y validado en logout + otros POST.
 * La contraseña nunca se compara en texto plano (password_verify contra un hash bcrypt).
 */

function auth_iniciar_sesion_php(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'samesite' => 'Lax']);
    session_start();
}

function auth_verificar_credenciales(string $username, string $password): bool
{
    $credenciales = require __DIR__ . '/credenciales.php';
    if (!hash_equals($credenciales['username'], $username)) {
        return false;
    }
    return password_verify($password, $credenciales['password_hash']);
}

function auth_marcar_autenticado(string $username): void
{
    session_regenerate_id(true);
    $_SESSION['username'] = $username;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function auth_esta_autenticado(): bool
{
    return isset($_SESSION['username']);
}

function auth_usuario_actual(): ?string
{
    return $_SESSION['username'] ?? null;
}

function auth_cerrar_sesion(): void
{
    $_SESSION = [];
    session_destroy();
}

function auth_obtener_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function auth_validar_csrf_token(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
