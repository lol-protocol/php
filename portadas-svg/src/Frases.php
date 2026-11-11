<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Las frases que el generador escribe por su cuenta (la ficha de la contratapa, «Traducción de…»), en cada idioma
 * que tiene sus textos en datos/frases.json. Un idioma que no esté se escribe en español. Los nombres de las
 * tradiciones y las categorías se traducen aquí; si falta uno, vale el del catálogo.
 */
final class Frases
{
    private const RESPALDO = 'es';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $datos = null;

    /**
     * Los idiomas con frases propias, por su código de dos letras.
     *
     * @return list<string>
     */
    public static function idiomas(): array
    {
        return array_keys(self::datos());
    }

    /** @param array<string, string|int> $variables lo que va en cada `{nombre}` de la frase */
    public static function texto(string $idioma, string $clave, array $variables = []): string
    {
        $datos = self::datos();
        $frase = $datos[self::codigo($idioma)][$clave] ?? $datos[self::RESPALDO][$clave] ?? throw new \LogicException("Falta la frase '$clave' en datos/frases.json.");
        if (!is_string($frase)) {
            throw new \LogicException("La frase '$clave' de datos/frases.json no es un texto.");
        }
        $reemplazos = [];
        foreach ($variables as $nombre => $valor) {
            $reemplazos['{' . $nombre . '}'] = (string) $valor;
        }

        return strtr($frase, $reemplazos);
    }

    /** El nombre de una tradición (`tradiciones`), una categoría (`categorias`) o una familia (`familias`) en ese idioma. */
    public static function nombre(string $idioma, string $tipo, string $clave, string $porDefecto): string
    {
        $tabla = self::datos()[self::codigo($idioma)][$tipo] ?? null;
        $nombre = is_array($tabla) ? ($tabla[$clave] ?? null) : null;

        return is_string($nombre) ? $nombre : $porDefecto;
    }

    private static function codigo(string $idioma): string
    {
        $codigo = strtolower(substr($idioma, 0, 2));

        return isset(self::datos()[$codigo]) ? $codigo : self::RESPALDO;
    }

    /** @return array<string, array<string, mixed>> */
    private static function datos(): array
    {
        if (self::$datos !== null) {
            return self::$datos;
        }
        $contenido = file_get_contents(__DIR__ . '/../datos/frases.json');
        $datos = $contenido === false ? null : json_decode($contenido, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($datos) || !is_array($datos[self::RESPALDO] ?? null)) {
            throw new \RuntimeException('datos/frases.json no se puede leer o no trae el español.');
        }
        $idiomas = [];
        foreach ($datos as $idioma => $frases) {
            if (!is_array($frases)) {
                throw new \RuntimeException("datos/frases.json: «{$idioma}» debe ser un objeto con las frases del idioma.");
            }
            $propias = [];
            foreach ($frases as $clave => $frase) {
                $propias[(string) $clave] = $frase;
            }
            $idiomas[(string) $idioma] = $propias;
        }

        return self::$datos = $idiomas;
    }
}
