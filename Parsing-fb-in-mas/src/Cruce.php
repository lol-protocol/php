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

    /** Partículas de nombres: no cuentan como coincidencia («de la Cruz» no hace parecidas a dos personas). */
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'e', 'da', 'das', 'do', 'dos', 'di', 'du', 'le',
        'van', 'von', 'der', 'den', 'ten', 'ter', 'bin', 'ibn', 'al'];

    /**
     * @param list<array<string, mixed>> $a perfiles (la salida de `aArreglo()`)
     * @param list<array<string, mixed>> $b
     * @param string $idioma código de tres letras del plegado fonético (`spa`, `eng`…)
     * @return array{candidatos: list<array{a: array<string, mixed>, b: array<string, mixed>, puntaje: float, evidencia: list<string>, estado: string}>, excluidos_vivos: int}
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
                // El nombre de pila de cada uno debe estar en el otro: «María de la Cruz» no es «José de la Cruz».
                if ($cuantas < 2 || !in_array($x['primero'], $y['palabras'], true) || !in_array($y['primero'], $x['palabras'], true)) {
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
     * @return array{palabras: list<string>, primero: ?string, anio: ?int, lugares: list<string>}
     */
    private static function ficha(array $p, string $idioma): array
    {
        $anio = null;
        if (is_string($p['nacimiento'] ?? null) && preg_match('/^(\d{4})/', $p['nacimiento'], $m) === 1) {
            $anio = (int) $m[1];
        }
        $palabras = self::plegar(is_string($p['nombre'] ?? null) ? $p['nombre'] : '', $idioma);
        return [
            'palabras' => $palabras,
            'primero' => $palabras[0] ?? null,
            'anio' => $anio,
            'lugares' => self::lugarEspecifico(is_string($p['lugar_nacimiento'] ?? null) ? $p['lugar_nacimiento'] : '', $idioma),
        ];
    }

    /**
     * Solo la parte más específica del lugar («Valparaíso» de «Valparaíso, Chile»). Un lugar que
     * es solo un país o una región no prueba nada, y un identificador de Wikidata (`Q2887`) se
     * compara entero.
     *
     * @return list<string>
     */
    private static function lugarEspecifico(string $lugar, string $idioma): array
    {
        $lugar = trim($lugar);
        if (preg_match('/^Q\d+$/', $lugar) === 1) {
            return [$lugar];
        }
        $partes = array_map('trim', explode(',', $lugar));
        return count($partes) < 2 ? [] : self::plegar($partes[0], $idioma);
    }

    /**
     * Palabras del texto sin partículas, plegadas fonéticamente y sin repetir, en orden.
     *
     * @return list<string>
     */
    private static function plegar(string $texto, string $idioma): array
    {
        $palabras = [];
        foreach (preg_split('/[\s,.\-]+/u', mb_strtolower($texto)) ?: [] as $palabra) {
            if (mb_strlen($palabra) >= 2 && !in_array($palabra, self::PARTICULAS, true)) {
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
