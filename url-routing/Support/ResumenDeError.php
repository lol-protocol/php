<?php

declare(strict_types=1);

namespace App\Support;

/**
 * How an exception is written in the log. Its message is the one part that can carry what must not be kept in a
 * log: a failed connection names the host and the user, a unique violation quotes the row («Key (id)=(...)»),
 * a query that failed may repeat a name. So the log gets where it happened and what kind of error it was (the
 * class, the SQLSTATE or code, the file and the line), which is enough to find it; the message itself only
 * when DEBUG=true, on a machine where someone is looking.
 */
final class ResumenDeError
{
    public static function de(\Throwable $e, ?bool $depuracion = null): string
    {
        $depuracion ??= defined('DEBUG_MODE') && DEBUG_MODE;
        $codigo = (string)$e->getCode() !== '' && (string)$e->getCode() !== '0' ? ' [' . $e->getCode() . ']' : '';

        return $depuracion
            ? $e::class . $codigo . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine()
            : $e::class . $codigo . ' en ' . basename($e->getFile()) . ':' . $e->getLine();
    }
}
