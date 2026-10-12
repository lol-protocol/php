<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Who may see what is private.
 *
 * The app has no user accounts, so there is exactly one privileged identity,
 * the owner, and it is proven with a shared secret: OWNER_TOKEN. The browser
 * sends it as the password of HTTP Basic auth (any user name) and API clients
 * as `Authorization: Bearer <token>`. Credentials are checked on every request
 * that carries them; once they are accepted the owner is also remembered in
 * the session, so browsing on needs no header (see recordar()).
 *
 * It fails closed. With no OWNER_TOKEN, or one shorter than MIN_TOKEN_LENGTH,
 * nobody is the owner and everything private stays private, so forgetting to
 * configure it never opens anything. Send the token only over HTTPS.
 *
 * Two ways to refuse, because they answer different questions:
 *  - ocultar(): 404, identical to a page that doesn't exist. For private data
 *    whose very existence must not be confirmed (ids are guessable URLs).
 *  - pedirCredenciales(): 401, which makes a browser ask for the token. For
 *    places that are known to exist, like the account area (/0/), which is
 *    also where the owner logs in.
 */
final class AccessPolicy
{
    /** A shorter secret is rejected as if it were not set: it would be guessable. */
    public const MIN_TOKEN_LENGTH = 20;

    public const REALM = 'Acceso privado';

    /** The owner's session ends after this long without a request... */
    public const INACTIVIDAD_MAXIMA = 3600;

    /** ...and in any case this long after it started. */
    public const DURACION_MAXIMA = 43200;

    private const CLAVE_SESION = 'propietario';

    /** Byte for byte what BaseController::handleNotFound() sends, so a hidden page is indistinguishable from a missing one. */
    public const CUERPO_404 = '<h1>404 - Página no encontrada</h1>';

    /** Whether $token is a usable OWNER_TOKEN. */
    public static function tokenValido(?string $token): bool
    {
        return $token !== null && strlen($token) >= self::MIN_TOKEN_LENGTH;
    }

    /**
     * The secret carried by an Authorization header: the password of a Basic
     * credential (the user name is ignored) or a Bearer token. Null when the
     * header is missing or is neither.
     */
    public static function credencial(?string $cabecera): ?string
    {
        if ($cabecera === null) {
            return null;
        }

        if (preg_match('/^Basic\s+([A-Za-z0-9+\/=]+)\s*$/i', $cabecera, $m) === 1) {
            $decodificado = base64_decode($m[1], true);
            if ($decodificado === false || !str_contains($decodificado, ':')) {
                return null;
            }
            // Only the first colon separates the user name; the password may contain more.
            return substr($decodificado, strpos($decodificado, ':') + 1);
        }

        if (preg_match('/^Bearer\s+(\S+)\s*$/i', $cabecera, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** Pure check, so it can be tested without touching the environment. */
    public static function autoriza(?string $token, ?string $cabecera): bool
    {
        return self::coincide($token, self::credencial($cabecera));
    }

    private static function coincide(?string $token, ?string $credencial): bool
    {
        // hash_equals compares in constant time, so the check leaks nothing about the token.
        return self::tokenValido($token) && $credencial !== null && hash_equals($token, $credencial);
    }

    /**
     * What the session stores to remember the owner. It is derived from the
     * token, so rotating OWNER_TOKEN ends every session that was opened with
     * the old one.
     */
    public static function marcaDeSesion(string $token): string
    {
        return hash_hmac('sha256', self::CLAVE_SESION, $token);
    }

    /** Pure check of a session's contents, for the same reason as autoriza(). */
    public static function sesionValida(array $sesion, ?string $token, int $ahora): bool
    {
        if (!self::tokenValido($token)) {
            return false;
        }

        $recuerdo = $sesion[self::CLAVE_SESION] ?? null;
        if (!is_array($recuerdo) || !is_string($recuerdo['marca'] ?? null)
            || !is_int($recuerdo['inicio'] ?? null) || !is_int($recuerdo['ultima'] ?? null)) {
            return false;
        }

        return hash_equals(self::marcaDeSesion($token), $recuerdo['marca'])
            && $ahora - $recuerdo['ultima'] <= self::INACTIVIDAD_MAXIMA
            && $ahora - $recuerdo['inicio'] <= self::DURACION_MAXIMA;
    }

    private static function token(): ?string
    {
        $token = Config::get('OWNER_TOKEN');

        return is_string($token) ? $token : null;
    }

    /** Whether this very request carries the owner's credentials. */
    private static function traeCredenciales(): bool
    {
        $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if (is_string($cabecera)) {
            return self::autoriza(self::token(), $cabecera);
        }

        // Some SAPIs hand over the parsed Basic password instead of the raw header.
        $clave = $_SERVER['PHP_AUTH_PW'] ?? null;

        return self::coincide(self::token(), is_string($clave) ? $clave : null);
    }

    /** Whether this request is the owner's: right credentials now, or a session they opened. */
    public static function esPropietario(): bool
    {
        return self::traeCredenciales() || self::sesionValida($_SESSION ?? [], self::token(), time());
    }

    /** Whether an owner could ever authenticate here, i.e. OWNER_TOKEN is set and long enough. */
    public static function configurado(): bool
    {
        return self::tokenValido(self::token());
    }

    /**
     * Remembers an owner who just proved who they are, and keeps an existing
     * session alive. Call it when esPropietario() was true. A new session gets
     * a fresh id so one planted before logging in cannot be reused.
     */
    public static function recordar(): void
    {
        $token = self::token();
        if (!self::tokenValido($token) || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $ahora = time();
        if (self::sesionValida($_SESSION, $token, $ahora)) {
            $_SESSION[self::CLAVE_SESION]['ultima'] = $ahora;
            return;
        }

        if (!self::traeCredenciales()) {
            return;
        }

        SessionManager::getInstance()->regenerateId();
        $_SESSION[self::CLAVE_SESION] = ['marca' => self::marcaDeSesion($token), 'inicio' => $ahora, 'ultima' => $ahora];
    }

    /**
     * A response that holds owner-only content, or that hides it, must never be
     * stored by a shared cache: the same URL answers differently depending on
     * who asks.
     */
    public static function marcarPrivada(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: private, no-store');
            header('Vary: Authorization', false);
        }
    }

    /** 404, indistinguishable from a page that does not exist. */
    public static function ocultar(): string
    {
        self::marcarPrivada();
        http_response_code(404);

        return self::CUERPO_404;
    }

    /**
     * 401 asking for the credentials, so the owner can log in from the very
     * link they opened. Without a usable OWNER_TOKEN nobody could answer the
     * challenge, so it is the same 404 as ocultar(), and the log says why.
     */
    public static function pedirCredenciales(): string
    {
        if (!self::configurado()) {
            Logger::getInstance()->warning('Página privada oculta: OWNER_TOKEN no está configurado o es demasiado corto', [
                'minimo' => self::MIN_TOKEN_LENGTH,
            ]);
            return self::ocultar();
        }

        self::marcarPrivada();
        if (!headers_sent()) {
            header('WWW-Authenticate: Basic realm="' . self::REALM . '", charset="UTF-8"');
        }
        http_response_code(401);

        return '<h1>401 - Acceso restringido</h1>';
    }
}
