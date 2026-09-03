<?php

declare(strict_types=1);

/**
 * Autenticación simple por sesión (un único usuario admin, sin roles ni registro).
 *
 * Pensada para un prototipo: sin tokens CSRF, sin límite de intentos, sin expiración
 * configurable. La contraseña nunca se compara en texto plano (password_verify contra
 * un hash bcrypt en credenciales.php).
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
