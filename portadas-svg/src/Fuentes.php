<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Las fuentes libres (licencia OFL, ver fuentes/) que se incrustan en cada SVG, para que el texto se vea
 * igual en cualquier máquina sin tenerlas instaladas. Van como @font-face con los datos en base64
 * dentro de un bloque de estilo del propio SVG: no hay HTML ni JS.
 */
final class Fuentes
{
    /**
     * Familia => nombre en CSS, pila de respaldo, rango de pesos que cubre la fuente variable y archivo
     * de cada estilo.
     */
    private const FAMILIAS = [
        'serif' => [
            'nombre' => 'Source Serif 4',
            'respaldo' => "Georgia, 'Times New Roman', serif",
            'pesos' => '200 900',
            'archivos' => [
                'normal' => 'fuentes/source-serif-4-latin-wght-normal.woff2',
                'italic' => 'fuentes/source-serif-4-latin-wght-italic.woff2',
            ],
        ],
        'sans' => [
            'nombre' => 'Inter',
            'respaldo' => "'Helvetica Neue', Helvetica, Arial, sans-serif",
            'pesos' => '100 900',
            'archivos' => [
                'normal' => 'fuentes/inter-latin-wght-normal.woff2',
            ],
        ],
    ];

    /** @var array<string, string> */
    private static array $base64 = [];

    /** Valor de `font-family`: la fuente incrustada y, por si falla, otras parecidas. */
    public static function pila(string $familia): string
    {
        $datos = self::familia($familia);

        return "'" . $datos['nombre'] . "', " . $datos['respaldo'];
    }

    /**
     * Bloque `<style>` con el @font-face de cada cara usada, o nada si no se usa ninguna.
     *
     * @param array<string, array{0: string, 1: string}> $caras pares [familia, estilo], por cualquier clave
     */
    public static function estilo(array $caras): string
    {
        $reglas = [];
        foreach ($caras as [$familia, $estilo]) {
            $datos = self::familia($familia);
            $archivo = $datos['archivos'][$estilo] ?? null;
            if ($archivo === null) {
                throw new \InvalidArgumentException("La familia '$familia' no tiene estilo '$estilo' en fuentes/.");
            }
            $reglas["$familia/$estilo"] = sprintf(
                "@font-face{font-family:'%s';font-style:%s;font-weight:%s;src:url(data:font/woff2;base64,%s) format('woff2')}",
                $datos['nombre'],
                $estilo,
                $datos['pesos'],
                self::base64($archivo),
            );
        }
        ksort($reglas);

        return $reglas === [] ? '' : "<style>\n" . implode("\n", $reglas) . "\n</style>\n";
    }

    /** @return array{nombre: string, respaldo: string, pesos: string, archivos: array<string, string>} */
    private static function familia(string $familia): array
    {
        return self::FAMILIAS[$familia] ?? throw new \InvalidArgumentException("Familia de fuente desconocida: '$familia'.");
    }

    private static function base64(string $archivo): string
    {
        if (!isset(self::$base64[$archivo])) {
            $contenido = file_get_contents(__DIR__ . '/../' . $archivo);
            if ($contenido === false) {
                throw new \RuntimeException("No se pudo leer $archivo.");
            }
            self::$base64[$archivo] = base64_encode($contenido);
        }

        return self::$base64[$archivo];
    }
}
