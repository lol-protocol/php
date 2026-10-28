<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Las fuentes libres (licencia OFL, ver fuentes/) que se incrustan en cada SVG, para que el texto se vea
 * igual en cualquier máquina sin tenerlas instaladas. Van como @font-face con los datos en base64
 * dentro de un bloque de estilo del propio SVG: no hay HTML ni JS.
 *
 * Cada cara (familia, estilo y peso) está cortada en trozos por alfabeto (ver datos/fuentes.json y
 * herramientas/preparar-fuentes.py), y cada trozo lleva su `unicode-range`. Una pieza incrusta solo los
 * trozos que los textos que dibuja necesitan: un texto en español lleva dos trozos de cada cara, no la
 * fuente entera.
 */
final class Fuentes
{
    /** Familia => nombre en CSS y pila de respaldo, por si falla la fuente incrustada. */
    private const FAMILIAS = [
        'serif' => ['nombre' => 'Source Serif 4', 'respaldo' => "Georgia, 'Times New Roman', serif"],
        'sans' => ['nombre' => 'Inter', 'respaldo' => "'Helvetica Neue', Helvetica, Arial, sans-serif"],
    ];

    /** @var array<string, string> */
    private static array $base64 = [];
    /** @var array<string, array<string, array<int, list<array{archivo: string, rangos: list<array{int, int}>, texto: string}>>>>|null */
    private static ?array $indice = null;

    /** Valor de `font-family`: la fuente incrustada y, por si falla, otras parecidas. */
    public static function pila(string $familia): string
    {
        $datos = self::familia($familia);

        return "'" . $datos['nombre'] . "', " . $datos['respaldo'];
    }

    /**
     * Bloque `<style>` con los @font-face que hacen falta, o nada si no se usa ninguno. Cada elemento es una
     * cara con el texto que se escribe con ella.
     *
     * @param array<string, array{familia: string, estilo: string, peso: int, texto: string}> $caras por cualquier clave
     */
    public static function estilo(array $caras): string
    {
        $reglas = [];
        foreach ($caras as $cara) {
            $nombre = self::familia($cara['familia'])['nombre'];
            $trozos = self::indice()[$cara['familia']][$cara['estilo']][$cara['peso']]
                ?? throw new \InvalidArgumentException("La familia '{$cara['familia']}' no tiene la cara {$cara['estilo']} {$cara['peso']} en fuentes/.");

            $codigos = array_values(array_map(static fn (string $letra): int => mb_ord($letra), array_unique(mb_str_split($cara['texto']))));
            foreach ($trozos as $trozo) {
                if (!self::cubreAlguna($trozo['rangos'], $codigos)) {
                    continue;
                }
                $reglas["{$cara['familia']}/{$cara['estilo']}/{$cara['peso']}/{$trozo['archivo']}"] = sprintf(
                    "@font-face{font-family:'%s';font-style:%s;font-weight:%d;src:url(data:font/woff2;base64,%s) format('woff2');unicode-range:%s}",
                    $nombre,
                    $cara['estilo'],
                    $cara['peso'],
                    self::base64($trozo['archivo']),
                    $trozo['texto'],
                );
            }
        }
        ksort($reglas);

        return $reglas === [] ? '' : "<style>\n" . implode("\n", $reglas) . "\n</style>\n";
    }

    /**
     * @param list<array{int, int}> $rangos
     * @param list<int> $codigos
     */
    private static function cubreAlguna(array $rangos, array $codigos): bool
    {
        foreach ($codigos as $codigo) {
            foreach ($rangos as [$desde, $hasta]) {
                if ($codigo >= $desde && $codigo <= $hasta) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{nombre: string, respaldo: string} */
    private static function familia(string $familia): array
    {
        return self::FAMILIAS[$familia] ?? throw new \InvalidArgumentException("Familia de fuente desconocida: '$familia'.");
    }

    /** @return array<string, array<string, array<int, list<array{archivo: string, rangos: list<array{int, int}>, texto: string}>>>> */
    private static function indice(): array
    {
        if (self::$indice !== null) {
            return self::$indice;
        }
        $contenido = file_get_contents(__DIR__ . '/../datos/fuentes.json');
        $datos = $contenido === false ? null : json_decode($contenido, true, 16);
        if (!is_array($datos) || !is_array($datos['caras'] ?? null)) {
            throw new \RuntimeException('datos/fuentes.json no se puede leer o no trae las caras. Se genera con herramientas/preparar-fuentes.py.');
        }

        $indice = [];
        foreach ($datos['caras'] as $familia => $estilos) {
            foreach (is_array($estilos) ? $estilos : [] as $estilo => $pesos) {
                foreach (is_array($pesos) ? $pesos : [] as $peso => $trozos) {
                    foreach (is_array($trozos) ? $trozos : [] as $trozo) {
                        if (!is_array($trozo) || !is_string($trozo['archivo'] ?? null) || !is_string($trozo['rangos'] ?? null)) {
                            throw new \RuntimeException("datos/fuentes.json: un trozo de $familia/$estilo/$peso no tiene archivo y rangos.");
                        }
                        $indice[(string) $familia][(string) $estilo][(int) $peso][] = [
                            'archivo' => $trozo['archivo'],
                            'rangos' => self::rangos($trozo['rangos']),
                            'texto' => $trozo['rangos'],
                        ];
                    }
                }
            }
        }

        return self::$indice = $indice;
    }

    /**
     * Un `unicode-range` de CSS («U+20-7E,U+A0») como lista de pares [desde, hasta].
     *
     * @return list<array{int, int}>
     */
    private static function rangos(string $texto): array
    {
        $rangos = [];
        foreach (explode(',', $texto) as $parte) {
            if (preg_match('/^U\+([0-9A-F]+)(?:-([0-9A-F]+))?$/', trim($parte), $m) !== 1) {
                throw new \RuntimeException("datos/fuentes.json: rango no válido «{$parte}».");
            }
            $desde = (int) hexdec($m[1]);
            $rangos[] = [$desde, isset($m[2]) ? (int) hexdec($m[2]) : $desde];
        }

        return $rangos;
    }

    private static function base64(string $archivo): string
    {
        if (!isset(self::$base64[$archivo])) {
            $contenido = file_get_contents(__DIR__ . '/../fuentes/' . $archivo);
            if ($contenido === false) {
                throw new \RuntimeException("No se pudo leer fuentes/$archivo.");
            }
            self::$base64[$archivo] = base64_encode($contenido);
        }

        return self::$base64[$archivo];
    }
}
