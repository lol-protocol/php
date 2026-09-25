<?php

declare(strict_types=1);

namespace App;

/**
 * Proteccion CSRF minima: un token fijo por sesion que todo formulario POST
 * incluye oculto, y que el Router verifica antes de despachar cualquier
 * request POST. No depende de que otra parte de la app haya iniciado la
 * sesion antes: arranca la suya propia (con la misma cookie endurecida) la
 * primera vez que se pide un token, asi sigue funcionando aunque no exista
 * ningun login en la app.
 */
final class Csrf
{
    private const CLAVE_SESION = 'csrf_token';

    /** Token de la sesion actual; lo genera la primera vez que se pide. */
    public static function token(): string
    {
        self::iniciarSesion();
        if (empty($_SESSION[self::CLAVE_SESION])) {
            $_SESSION[self::CLAVE_SESION] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CLAVE_SESION];
    }

    /** Input oculto listo para pegar dentro de cualquier <form method="post">. */
    public static function campo(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** true si el token recibido por POST coincide con el de la sesion. */
    public static function valido(): bool
    {
        $recibido = (string) ($_POST['csrf_token'] ?? '');
        return $recibido !== '' && hash_equals(self::token(), $recibido);
    }

    /**
     * Arranca la sesion de PHP si todavia no esta activa. Mismos parametros
     * de cookie que se usaban para el login (HttpOnly + SameSite=Lax +
     * Secure solo por HTTPS): eran endurecimientos genericos de la cookie de
     * sesion, no algo especifico de tener una cuenta logueada.
     */
    private static function iniciarSesion(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => Http::esSegura(),
            ]);
            session_start();
        }
    }
}
