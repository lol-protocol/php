<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;

/** Guard clauses repetidas en los controllers que buscan una entidad por id antes de operar sobre ella. */
final class Peticion
{
    /**
     * Descarta los parametros de $_GET/$_POST que no sean escalares. Ninguna
     * pantalla usa parametros repetidos, asi que un "?page[]=x" solo puede
     * venir de una URL armada a mano; sin esto revienta la primera firma
     * tipada que lo reciba y sale un 500 (Router::dispatch() lo hacia incluso
     * sin sesion iniciada, y "?estado[]=x" en Cobros lo mismo). Descartado el
     * parametro, cada pantalla cae en su valor por defecto.
     */
    public static function normalizarParametros(): void
    {
        $_GET = array_filter($_GET, is_scalar(...));
        $_POST = array_filter($_POST, is_scalar(...));
    }

    /**
     * Manda al navegador a $destino y termina la peticion. Es lo ultimo que hace una pantalla despues de
     * guardar (o de reconocer un reenvio): sin esto, cada una repetia header('Location: ...') seguido de
     * un exit que nadie podia olvidar. Al devolver `never`, el analisis sabe que el codigo de abajo no corre.
     */
    public static function redirigir(string $destino): never
    {
        header('Location: ' . $destino);
        exit;
    }

    /** El id de la ruta actual, ya sea de un link (?id=) o de un form (name="id"). */
    public static function id(): int
    {
        return (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    }

    /**
     * Si $entidad es null, manda un 404 con $mensaje y devuelve true (el
     * caller debe hacer return enseguida). Devuelve false si existe.
     *
     * @phpstan-assert-if-false !null $entidad
     */
    public static function abortarSiNoExiste(?array $entidad, string $mensaje): bool
    {
        if ($entidad !== null) {
            return false;
        }
        http_response_code(404);
        echo $mensaje;
        return true;
    }

    /**
     * Igual que abortarSiNoExiste() pero con 409, para bloquear una accion
     * cuando $condicion se cumple (ej. la boleta o el pago ya esta anulado).
     */
    public static function abortarSiConflicto(bool $condicion, string $mensaje): bool
    {
        if (!$condicion) {
            return false;
        }
        http_response_code(409);
        echo $mensaje;
        return true;
    }

    /**
     * El monto del formulario, tal como se escribio. Si el campo falta o no es un monto
     * (Dinero::deTexto(): con coma, con letras o con mas de dos decimales, que no se redondean) devuelve 0:
     * Validacion::faltanCampos() ya rechaza un monto fuera de rango con el mismo mensaje de "monto valido",
     * asi que el formulario vuelve con su error en vez de guardar otra cantidad.
     */
    public static function monto(string $campo = 'monto'): float
    {
        try {
            return Dinero::deTexto((string) ($_POST[$campo] ?? ''));
        } catch (InvalidArgumentException) {
            return 0.0;
        }
    }
}
