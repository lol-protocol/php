<?php

declare(strict_types=1);

namespace App;

/**
 * Proteccion CSRF minima: un token fijo por sesion que todo formulario POST
 * incluye oculto, y que el Router verifica antes de despachar cualquier
 * request POST (login incluido, por eso vive aca y no en Auth).
 */
final class Csrf
{
    private const CLAVE_SESION = 'csrf_token';

    /** Token de la sesion actual; lo genera la primera vez que se pide. */
    public static function token(): string
    {
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
}
