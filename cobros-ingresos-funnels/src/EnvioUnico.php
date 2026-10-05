<?php

declare(strict_types=1);

namespace App;

use PDOException;

/**
 * Que un doble clic en "Guardar" no cree dos pagos o dos boletas.
 *
 * Cada formulario de alta lleva un token al azar de un solo uso (campo()).
 * El POST que lo trae corre la escritura con ejecutar(), que registra el
 * token en envios_formulario en la misma transaccion, junto con a donde
 * redirigir. Un reenvio del mismo formulario encuentra el token ya usado
 * (redireccionPrevia()) y recibe la misma redireccion, sin volver a validar
 * ni a crear nada.
 *
 * No alcanza con deshabilitar el boton: la CSP bloquea todo JavaScript
 * (script-src 'none'), y aunque no lo hiciera, el servidor no puede depender
 * del navegador para no duplicar plata. Tampoco alcanza el token CSRF: es uno
 * por sesion y no se consume, asi que el segundo envio lo pasa igual.
 */
final class EnvioUnico
{
    public const CAMPO = 'envio_token';

    /** Para un POST sin token valido: un formulario viejo (de antes de este cambio) o armado a mano. */
    public const MENSAJE_SIN_TOKEN = 'El formulario está incompleto o venció. Recargá la página y volvé a intentar.';

    private const DIAS_DE_RETENCION = 7;

    /** Input oculto para pegar en el <form> de un alta, junto a Csrf::campo(). */
    public static function campo(): string
    {
        return '<input type="hidden" name="' . self::CAMPO . '" value="' . bin2hex(random_bytes(32)) . '">';
    }

    /** El token del POST actual, o null si falta o no tiene el formato de campo(). */
    public static function tokenRecibido(): ?string
    {
        $token = (string) ($_POST[self::CAMPO] ?? '');

        return preg_match('/^[0-9a-f]{64}$/', $token) === 1 ? $token : null;
    }

    /** A donde redirigio el envio que ya uso este token, o null si todavia no se uso. */
    public static function redireccionPrevia(string $token): ?string
    {
        $stmt = Database::connection()->prepare('SELECT redireccion FROM envios_formulario WHERE token = :token');
        $stmt->execute([':token' => $token]);
        $redireccion = $stmt->fetchColumn();

        return $redireccion === false ? null : (string) $redireccion;
    }

    /**
     * Corre $operacion -que escribe y devuelve la URL a la que redirigir- y
     * registra el token en la misma transaccion. Devuelve la URL.
     *
     * Si otro envio con el mismo token commiteo primero (los dos llegaron a
     * la vez), el INSERT del token choca contra la clave primaria, la
     * transaccion entera se deshace -el registro duplicado incluido- y se
     * devuelve la redireccion del que gano.
     *
     * @param callable(): string $operacion
     */
    public static function ejecutar(string $token, callable $operacion): string
    {
        try {
            $redireccion = Database::transaccion(static function () use ($token, $operacion): string {
                $redireccion = $operacion();
                Database::connection()
                    ->prepare('INSERT INTO envios_formulario (token, redireccion) VALUES (:token, :redireccion)')
                    ->execute([':token' => $token, ':redireccion' => $redireccion]);

                return $redireccion;
            });
        } catch (PDOException $e) {
            $previa = $e->getCode() === '23505' ? self::redireccionPrevia($token) : null;
            if ($previa === null) {
                throw $e;
            }

            return $previa;
        }

        self::purgarViejos();

        return $redireccion;
    }

    /** Un token sirve para reconocer reenvios cercanos; pasada una semana ya no aporta nada. */
    private static function purgarViejos(): void
    {
        Database::connection()->exec(
            'DELETE FROM envios_formulario WHERE creado_en < now() - interval \'' . self::DIAS_DE_RETENCION . ' days\''
        );
    }
}
