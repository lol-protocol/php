<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use RuntimeException;

/**
 * Ayuda para ajustar un analizador a una página real sin tener que mirar el HTML a mano:
 *
 * - {@see informe()}: qué analizador reconoció la página y qué campos le sacó (solo sí o no, sin valores; la ruta
 *   de la URL, donde suele ir el usuario, sale enmascarada).
 * - {@see esqueleto()}: la estructura que ve el analizador (qué etiquetas hay y qué forma tienen sus valores)
 *   con las letras cambiadas por «x» y los dígitos por «9», conservando el largo y la puntuación. Se puede
 *   compartir sin compartir el nombre, el usuario ni la biografía de nadie.
 */
final class Diagnostico
{
    /** Los campos de un perfil que dependen de lo que traiga la página. */
    private const CAMPOS = ['usuario', 'nombre', 'titular', 'lugar', 'descripcion', 'nacimiento', 'muerte', 'lugar_nacimiento', 'lugar_muerte'];

    /**
     * @return array{analizador: ?string, url: ?string, etiquetas: list<string>, datos_estructurados: array{json_ld: bool, microdatos: bool, persona: bool}, campos: array<string, bool>, avisos: list<string>}
     */
    public static function informe(string $html): array
    {
        $datos = Metadatos::leer($html);
        $informe = [
            'analizador' => null,
            'url' => ($url = Analizador::urlDe($datos)) === null ? null : self::enmascararUrl($url),
            'etiquetas' => array_keys($datos['meta']),
            'datos_estructurados' => [
                'json_ld' => str_contains($html, 'application/ld+json'),
                'microdatos' => str_contains($html, 'itemscope'),
                'persona' => $datos['persona'] !== null,
            ],
            'campos' => array_fill_keys(self::CAMPOS, false),
            'avisos' => [],
        ];
        try {
            $perfil = Detector::analizar($html, null, false)->aArreglo();
        } catch (RuntimeException $e) {
            $informe['avisos'][] = $e->getMessage();
            return $informe;
        }
        $informe['analizador'] = $perfil['red'];
        foreach (self::CAMPOS as $campo) {
            $informe['campos'][$campo] = $perfil[$campo] !== null;
        }
        $informe['avisos'] = $perfil['avisos'];
        return $informe;
    }

    /**
     * La estructura de la página, sin datos de nadie. Una línea por dato: `url`, `etiqueta <nombre> = <valor enmascarado>`
     * y `persona.<campo> = <valor enmascarado>`.
     */
    public static function esqueleto(string $html): string
    {
        $datos = Metadatos::leer($html);
        $lineas = [];
        $url = Analizador::urlDe($datos);
        if ($url !== null) {
            $lineas[] = 'url = ' . self::enmascararUrl($url);
        }
        if ($datos['titulo'] !== null) {
            $lineas[] = 'title = ' . self::enmascarar($datos['titulo']);
        }
        foreach ($datos['meta'] as $clave => $valor) {
            $lineas[] = "etiqueta $clave = " . self::enmascarar($valor);
        }
        foreach ($datos['persona'] ?? [] as $campo => $valor) {
            $lineas[] = "persona.$campo = " . self::enmascarar($valor);
        }
        return implode("\n", $lineas) . "\n";
    }

    /** Letras por «x» y dígitos por «9»; todo lo demás, igual. */
    public static function enmascarar(string $texto): string
    {
        return (string) preg_replace(['/\p{L}/u', '/\p{N}/u'], ['x', '9'], $texto);
    }

    /** Conserva el esquema y el dominio (no son personales) y enmascara la ruta, donde suele ir el usuario. */
    private static function enmascararUrl(string $url): string
    {
        if (preg_match('#^(https?://[^/?\#]+)(.*)$#i', $url, $m) !== 1) {
            return self::enmascarar($url);
        }
        return $m[1] . self::enmascarar($m[2]);
    }
}
