<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/** Lectura segura de JSON decodificado: un dato ausente o de otro tipo da null, nunca un error. */
final class Json
{
    /**
     * `Json::en($e, 'claims', 'P31')` es `$e['claims']['P31']`, o null si algún paso falta o no es un arreglo.
     */
    public static function en(mixed $valor, string ...$ruta): mixed
    {
        foreach ($ruta as $clave) {
            if (!is_array($valor) || !array_key_exists($clave, $valor)) {
                return null;
            }
            $valor = $valor[$clave];
        }
        return $valor;
    }

    public static function texto(mixed $valor, string ...$ruta): ?string
    {
        $v = self::en($valor, ...$ruta);
        return is_string($v) ? $v : null;
    }
}
