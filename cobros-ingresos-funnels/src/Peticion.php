<?php

declare(strict_types=1);

namespace App;

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
     * cuando $condicion se cumple (ya anulado, revocarse a uno mismo, etc.).
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
}
