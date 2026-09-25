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
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
    session_start();
}

/**
 * Hash bcrypt válido pero que no corresponde a ninguna cuenta real: si el
 * usuario no existe, auth_verificar_credenciales() igual corre password_verify()
 * contra esto en vez de cortar antes, para que el tiempo de respuesta no filtre
 * qué usuarios existen (antes, con el único usuario hardcodeado en credenciales.php,
 * ese mismo rol lo cumplía comparar el username con hash_equals()).
 */
const AUTH_HASH_DUMMY = '$2y$12$5oCNuEG9IhDV77jb03nkXOiEpNTSb2cgl4.G.FvSbzC9t.3au48xC';

function auth_verificar_credenciales(PDO $pdo, string $username, string $password): bool
{
    $claveHash = (new AlmacenAdministradores($pdo))->claveHash($username);
    return password_verify($password, $claveHash ?? AUTH_HASH_DUMMY);
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
    if ($token === '' || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/** CSRF vía header (X-CSRF-Token): así cubre POST y DELETE por igual, sin depender de si hay body. */
function auth_validar_csrf_header(): bool
{
    return auth_validar_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
}
