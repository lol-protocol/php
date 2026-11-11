<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Tablas de anchos de las fuentes que incrusta el generador, leídas de datos/anchos.json. Ese archivo
 * lo produce herramientas/preparar-fuentes.py; aquí solo se lee.
 */
final class Anchos
{
    private const RUTA = __DIR__ . '/../datos/anchos.json';
    private const KERNING = __DIR__ . '/../datos/kerning.json';

    /** @var array<string, MedidaFuente> */
    private static array $caras = [];
    /** @var array<mixed>|null el contenido de datos/anchos.json, leído una sola vez para todas las caras */
    private static ?array $datos = null;

    /** La medida de una cara: familia (serif o sans), estilo (normal o italic) y peso (400, 700…). */
    public static function de(string $familia, string $estilo, int $peso): MedidaFuente
    {
        return self::$caras["$familia/$estilo/$peso"] ??= self::cargar($familia, $estilo, $peso);
    }

    /** Olvida lo leído: hace falta al cambiar de fuentes extra (ver Fuentes::usarExtras). */
    public static function reiniciar(): void
    {
        self::$caras = [];
    }

    /** @return array<mixed> */
    private static function leer(): array
    {
        if (self::$datos !== null) {
            return self::$datos;
        }
        $contenido = file_get_contents(self::RUTA);
        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer datos/anchos.json. Se genera con herramientas/preparar-fuentes.py.');
        }
        $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);

        return self::$datos = is_array($datos) ? $datos : [];
    }

    /**
     * El kerning de una cara, de datos/kerning.json. Sin ese archivo, o sin esa cara, no hay ajuste.
     *
     * @return array<string, int>
     */
    private static function pares(string $familia, string $estilo, int $peso, int $unidades): array
    {
        $contenido = is_file(self::KERNING) ? file_get_contents(self::KERNING) : false;
        $datos = $contenido === false ? null : json_decode($contenido, true, 16);
        $caras = is_array($datos) && ($datos['unidades'] ?? null) === $unidades && is_array($datos['caras'] ?? null) ? $datos['caras'] : [];
        $porFamilia = is_array($caras[$familia] ?? null) ? $caras[$familia] : [];
        $porEstilo = is_array($porFamilia[$estilo] ?? null) ? $porFamilia[$estilo] : [];
        $pares = $porEstilo[(string) $peso] ?? null;
        if (!is_array($pares)) {
            return [];
        }

        $resultado = [];
        foreach ($pares as $par => $ajuste) {
            if (is_int($ajuste)) {
                $resultado[(string) $par] = $ajuste;
            }
        }

        return $resultado;
    }

    private static function cargar(string $familia, string $estilo, int $peso): MedidaFuente
    {
        $datos = self::leer();

        $caras = $datos['caras'] ?? null;
        $porFamilia = is_array($caras) ? ($caras[$familia] ?? null) : null;
        $porEstilo = is_array($porFamilia) ? ($porFamilia[$estilo] ?? null) : null;
        $cara = is_array($porEstilo) ? ($porEstilo[(string) $peso] ?? null) : null;
        $unidades = $datos['unidades'] ?? null;
        if (!is_array($cara) || !is_array($cara['anchos'] ?? null) || !is_int($cara['promedio'] ?? null) || !is_int($unidades)) {
            throw new \RuntimeException("datos/anchos.json no tiene la cara $familia/$estilo/$peso. Añade esa cara a FAMILIAS en herramientas/preparar-fuentes.py y vuelve a correrla.");
        }

        $anchos = [];
        foreach ($cara['anchos'] as $codigo => $ancho) {
            if (is_int($ancho)) {
                $anchos[(int) $codigo] = $ancho;
            }
        }

        $extras = [];
        foreach (Fuentes::extras() as $codigo => [$avance]) {
            $extras[$codigo] = $avance;
        }

        $formas = [];
        foreach (Fuentes::formas() as $palabra => [$avance]) {
            $formas[(string) $palabra] = $avance;
        }

        return new MedidaFuente($anchos, $cara['promedio'], $unidades, $extras, $formas, self::pares($familia, $estilo, $peso, $unidades));
    }
}
