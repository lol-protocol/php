<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Tablas de anchos de las fuentes que incrusta el generador, leídas de datos/anchos.json. Ese archivo
 * lo produce herramientas/medir-fuentes.py; aquí solo se lee.
 */
final class Anchos
{
    private const RUTA = __DIR__ . '/../datos/anchos.json';

    /** @var array<string, MedidaFuente> */
    private static array $caras = [];

    /** La medida de una cara: familia (serif o sans), estilo (normal o italic) y peso (400, 700…). */
    public static function de(string $familia, string $estilo, int $peso): MedidaFuente
    {
        return self::$caras["$familia/$estilo/$peso"] ??= self::cargar($familia, $estilo, $peso);
    }

    private static function cargar(string $familia, string $estilo, int $peso): MedidaFuente
    {
        $contenido = file_get_contents(self::RUTA);
        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer datos/anchos.json. Se genera con herramientas/medir-fuentes.py.');
        }
        $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);

        $caras = is_array($datos) ? ($datos['caras'] ?? null) : null;
        $porFamilia = is_array($caras) ? ($caras[$familia] ?? null) : null;
        $porEstilo = is_array($porFamilia) ? ($porFamilia[$estilo] ?? null) : null;
        $cara = is_array($porEstilo) ? ($porEstilo[(string) $peso] ?? null) : null;
        $unidades = is_array($datos) ? ($datos['unidades'] ?? null) : null;
        if (!is_array($cara) || !is_array($cara['anchos'] ?? null) || !is_int($cara['promedio'] ?? null) || !is_int($unidades)) {
            throw new \RuntimeException("datos/anchos.json no tiene la cara $familia/$estilo/$peso. Añade ese peso a PESOS en herramientas/medir-fuentes.py y vuelve a correrla.");
        }

        $anchos = [];
        foreach ($cara['anchos'] as $codigo => $ancho) {
            if (is_int($ancho)) {
                $anchos[(int) $codigo] = $ancho;
            }
        }

        return new MedidaFuente($anchos, $cara['promedio'], $unidades);
    }
}
