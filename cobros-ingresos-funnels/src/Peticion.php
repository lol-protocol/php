<?php

declare(strict_types=1);

namespace App;

/** Guard clauses repetidas en los controllers que buscan una entidad por id antes de operar sobre ella. */
final class Peticion
{
    /** El id de la ruta actual, ya sea de un link (?id=) o de un form (name="id"). */
    public static function id(): int
    {
        return (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    }

    /**
     * Si $entidad es null, manda un 404 con $mensaje y devuelve true (el
     * caller debe hacer return enseguida). Devuelve false si existe.
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
