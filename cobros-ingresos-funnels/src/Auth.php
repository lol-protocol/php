<?php

declare(strict_types=1);

namespace App;

use App\Repositories\IntentoLoginRepository;
use App\Repositories\UsuarioSistemaRepository;

final class Auth
{
    public static function iniciar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function usuarioActual(): ?array
    {
        self::iniciar();
        return $_SESSION['usuario'] ?? null;
    }

    public static function autenticado(): bool
    {
        return self::usuarioActual() !== null;
    }

    /**
     * Intenta loguear. Devuelve 'ok', 'bloqueado' (demasiados intentos
     * fallidos seguidos) o 'invalido' (email/password incorrectos).
     */
    public static function intentarLogin(string $email, string $password): string
    {
        $intentos = new IntentoLoginRepository();
        if ($intentos->minutosDeBloqueo($email) !== null) {
            return 'bloqueado';
        }

        $usuario = (new UsuarioSistemaRepository())->porEmail($email);
        if ($usuario === null || !password_verify($password, $usuario['password_hash'])) {
            $intentos->registrarFallo($email);
            return 'invalido';
        }

        $intentos->limpiar($email);
        self::iniciar();
        session_regenerate_id(true);
        $_SESSION['usuario'] = ['id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'email' => $usuario['email']];
        return 'ok';
    }

    public static function minutosDeBloqueo(string $email): ?int
    {
        return (new IntentoLoginRepository())->minutosDeBloqueo($email);
    }

    public static function logout(): void
    {
        self::iniciar();
        $_SESSION = [];
        session_destroy();
    }

    /** Corta la ejecucion y redirige a login (con destino de vuelta) si no hay sesion activa. */
    public static function requerir(): void
    {
        if (!self::autenticado()) {
            $destino = $_SERVER['REQUEST_URI'] ?? '?page=dashboard';
            header('Location: ?page=login&next=' . urlencode($destino));
            exit;
        }
    }

    /**
     * Valida que $destino sea una ruta local segura de esta misma app
     * (nunca una URL externa), para evitar un open redirect tras el login.
     */
    public static function destinoSeguro(?string $destino): string
    {
        if ($destino === null || !preg_match('#^/\?page=[a-zA-Z0-9_=&-]*$#', $destino)) {
            return '?page=dashboard';
        }
        return $destino;
    }
}
