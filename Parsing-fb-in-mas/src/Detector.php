<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use RuntimeException;

/** Elige el analizador según el dominio de la página guardada. */
final class Detector
{
    /**
     * @return list<Analizador>
     */
    public static function analizadores(): array
    {
        return [new Facebook(), new LinkedIn(), new Twitter(), new Generico()];
    }

    /**
     * @throws RuntimeException si la página no es de una red conocida ni declara una `Person`
     */
    public static function analizar(string $html, ?string $capturado = null): Perfil
    {
        $datos = Metadatos::leer($html);
        foreach (self::analizadores() as $analizador) {
            if ($analizador->reconoce($datos)) {
                return $analizador->desdeMetadatos($datos, $capturado);
            }
        }
        throw new RuntimeException('No reconozco la página: ni es de una red conocida ni declara una Person de schema.org.');
    }
}
