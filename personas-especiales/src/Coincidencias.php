<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/**
 * Coincidencias de apellido con las personas excluidas, para que una persona revise.
 *
 * Esto NO oculta a nadie: marca posibles homónimos o familiares para que alguien los
 * revise. Ocultar por apellido tendría falsos positivos (una familia entera), así que
 * la decisión queda en una persona.
 *
 * Regla: dos apellidos coinciden si, normalizados, son iguales y tienen al menos
 * MINIMO letras. Sin coincidencia fonética ni difusa en esta versión: cada una agrega
 * falsos positivos y se decide aparte.
 */
final class Coincidencias
{
    public const MINIMO = 3;

    /** Tildes, diéresis y eñes del español y de lenguas cercanas, a su letra base. */
    private const LETRAS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
    ];

    /** Minúsculas, sin tildes ni signos, con espacios simples. */
    public static function normalizar(string $apellido): string
    {
        $texto = mb_strtolower(trim($apellido), 'UTF-8');
        $texto = strtr($texto, self::LETRAS);
        // El apóstrofo se borra («O'Brien» es «OBrien»); lo demás que no sea letra separa palabras
        // («Sánchez-Vidal» es «sanchez vidal»). \p{L} conserva las letras de otros alfabetos.
        $texto = str_replace(["'", '’', '`'], '', $texto);
        $texto = (string) preg_replace('/[^\p{L} ]+/u', ' ', $texto);
        $texto = (string) preg_replace('/\s+/', ' ', $texto);

        return trim($texto);
    }

    /** ¿Coincide con alguno de los apellidos excluidos? Ver la regla en la descripción de la clase. */
    public static function coincide(string $apellido, string $apellidoExcluido): bool
    {
        $a = self::normalizar($apellido);
        $b = self::normalizar($apellidoExcluido);

        return mb_strlen($a, 'UTF-8') >= self::MINIMO && $a === $b;
    }

    /**
     * Índices de los apellidos excluidos que coinciden con el apellido consultado.
     * Vacío si no hay ninguno. No dice nada de quién está en el registro.
     *
     * @param list<string> $apellidosExcluidos
     * @return list<int>
     */
    public static function indicesCoincidentes(string $apellido, array $apellidosExcluidos): array
    {
        $indices = [];
        foreach ($apellidosExcluidos as $i => $excluido) {
            if (self::coincide($apellido, $excluido)) {
                $indices[] = $i;
            }
        }

        return $indices;
    }
}
