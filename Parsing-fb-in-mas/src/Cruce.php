<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use DefamatoryContentReview\Phonetic\PhoneticFolderRegistry;

/**
 * Propone qué registros de personas FALLECIDAS de dos fuentes distintas (Wikidata, WikiTree,
 * Find a Grave…) podrían ser la misma persona. Solo propone: nunca fusiona, y cada pareja
 * pide revisión humana.
 *
 * Por diseño no cruza personas que pueden estar vivas: un perfil de red social no se
 * empareja con registros de fallecidos ni con nada (ver PRIVACIDAD-Y-HONRA.md).
 * Usa el plegado fonético de `defamatory-content-review/` para que «Gonzalez» y «Gonsales»
 * coincidan.
 */
final class Cruce
{
    private const PUNTAJE_MINIMO = 0.6;

    /**
     * @param list<array<string, mixed>> $a perfiles (la salida de `aArreglo()`)
     * @param list<array<string, mixed>> $b
     * @param string $idioma código de tres letras del plegado fonético (`spa`, `eng`…)
     * @return array{candidatos: list<array<string, mixed>>, excluidos_vivos: int}
     */
    public static function candidatos(array $a, array $b, string $idioma = 'spa'): array
    {
        $excluidos = 0;
        $fa = self::soloFallecidos($a, $excluidos);
        $fb = self::soloFallecidos($b, $excluidos);

        // Índice invertido: palabra plegada → posiciones en $fb. Evita comparar todo con todo.
        $indice = [];
        $fichasB = [];
        foreach ($fb as $i => $p) {
            $fichasB[$i] = self::ficha($p, $idioma);
            foreach ($fichasB[$i]['palabras'] as $palabra) {
                $indice[$palabra][] = $i;
            }
        }

        $candidatos = [];
        foreach ($fa as $p) {
            $x = self::ficha($p, $idioma);
            $compartidas = [];
            foreach ($x['palabras'] as $palabra) {
                foreach ($indice[$palabra] ?? [] as $i) {
                    $compartidas[$i] = ($compartidas[$i] ?? 0) + 1;
                }
            }
            foreach ($compartidas as $i => $cuantas) {
                $y = $fichasB[$i];
                $union = count(array_unique([...$x['palabras'], ...$y['palabras']]));
                $puntaje = $cuantas / $union;
                $evidencia = [$cuantas . ' palabras del nombre coinciden (plegado fonético)'];
                if ($cuantas < 2) {
                    continue;
                }
                if ($x['anio'] !== null && $y['anio'] !== null) {
                    if (abs($x['anio'] - $y['anio']) > 5) {
                        continue;
                    }
                    if (abs($x['anio'] - $y['anio']) <= 1) {
                        $puntaje += 0.2;
                        $evidencia[] = 'nacimientos con un año de diferencia o menos';
                    }
                }
                if (array_intersect($x['lugares'], $y['lugares']) !== []) {
                    $puntaje += 0.1;
                    $evidencia[] = 'comparten una palabra de lugar';
                }
                if ($puntaje < self::PUNTAJE_MINIMO) {
                    continue;
                }
                $candidatos[] = [
                    'a' => self::referencia($p),
                    'b' => self::referencia($fb[$i]),
                    'puntaje' => round(min($puntaje, 1.0), 2),
                    'evidencia' => $evidencia,
                    'estado' => 'candidato: requiere revisión humana, no es una fusión',
                ];
            }
        }
        usort($candidatos, static fn (array $p, array $q): int => $q['puntaje'] <=> $p['puntaje']);

        return ['candidatos' => $candidatos, 'excluidos_vivos' => $excluidos];
    }

    /**
     * @param list<array<string, mixed>> $perfiles
     * @return list<array<string, mixed>>
     */
    private static function soloFallecidos(array $perfiles, int &$excluidos): array
    {
        $salida = [];
        foreach ($perfiles as $p) {
            if (($p['posiblemente_vivo'] ?? true) !== false || ($p['muerte'] ?? null) === null) {
                $excluidos++;
                continue;
            }
            $salida[] = $p;
        }
        return $salida;
    }

    /**
     * @param array<string, mixed> $p
     * @return array{palabras: list<string>, anio: ?int, lugares: list<string>}
     */
    private static function ficha(array $p, string $idioma): array
    {
        $anio = null;
        if (is_string($p['nacimiento'] ?? null) && preg_match('/^(\d{4})/', $p['nacimiento'], $m) === 1) {
            $anio = (int) $m[1];
        }
        return [
            'palabras' => self::plegar(is_string($p['nombre'] ?? null) ? $p['nombre'] : '', $idioma),
            'anio' => $anio,
            'lugares' => self::plegar(is_string($p['lugar_nacimiento'] ?? null) ? $p['lugar_nacimiento'] : '', $idioma),
        ];
    }

    /**
     * @return list<string>
     */
    private static function plegar(string $texto, string $idioma): array
    {
        $palabras = [];
        foreach (preg_split('/[\s,.\-]+/u', mb_strtolower($texto)) ?: [] as $palabra) {
            if (mb_strlen($palabra) >= 2) {
                $palabras[] = PhoneticFolderRegistry::fold($idioma, $palabra);
            }
        }
        return array_values(array_unique($palabras));
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    private static function referencia(array $p): array
    {
        return ['red' => $p['red'] ?? null, 'url' => $p['url'] ?? null, 'nombre' => $p['nombre'] ?? null,
            'nacimiento' => $p['nacimiento'] ?? null, 'muerte' => $p['muerte'] ?? null];
    }
}
