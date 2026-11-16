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
    /** Partículas de nombres: no cuentan como coincidencia («de la Cruz» no hace parecidas a dos personas). */
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'e', 'da', 'das', 'do', 'dos', 'di', 'du', 'le',
        'van', 'von', 'der', 'den', 'ten', 'ter', 'bin', 'ibn', 'al'];

    /**
     * Cruza dos listas de perfiles. Para archivos grandes conviene {@see indexar()} la fuente B y {@see cruzar()}
     * la A por separado: así A se lee de un generador, sin cargarla entera.
     *
     * @param iterable<array<string, mixed>> $a perfiles (la salida de `aArreglo()`)
     * @param iterable<array<string, mixed>> $b
     * @param string $idioma código de tres letras del plegado fonético (`spa`, `eng`…)
     * @return array{candidatos: list<array{a: array<string, mixed>, b: array<string, mixed>, puntaje: float, evidencia: list<string>, estado: string}>, excluidos_vivos: int}
     */
    public static function candidatos(iterable $a, iterable $b, string $idioma = 'spa', ?OpcionesCruce $opciones = null): array
    {
        $indice = self::indexar($b, $idioma);
        $vivosA = 0;
        $candidatos = self::cruzar($a, $indice, $idioma, $opciones ?? new OpcionesCruce(), $vivosA);

        return ['candidatos' => $candidatos, 'excluidos_vivos' => $indice->omitidos + $vivosA];
    }

    /**
     * Recuerda de la fuente B solo lo necesario para comparar (ver {@see IndiceCruce}). Los perfiles que pueden
     * estar vivos no entran: se cuentan en `omitidos`.
     *
     * @param iterable<array<string, mixed>> $b
     */
    public static function indexar(iterable $b, string $idioma = 'spa'): IndiceCruce
    {
        $indice = new IndiceCruce();
        foreach ($b as $perfil) {
            if (!self::fallecido($perfil)) {
                $indice->omitidos++;
                continue;
            }
            $ficha = self::ficha($perfil, $idioma);
            $posicion = count($indice->fichas);
            $indice->fichas[] = $ficha + ['ref' => self::referencia($perfil)];
            if ($ficha['primero'] !== null) {
                $indice->porPrimero[$ficha['primero']][] = $posicion;
            }
        }
        return $indice;
    }

    /**
     * Compara cada perfil de A contra el índice de B y devuelve las parejas candidatas, de mayor a menor puntaje.
     *
     * @param iterable<array<string, mixed>> $a
     * @param int $omitidosA se le suma cuántos perfiles de A se omitieron por poder estar vivos
     * @return list<array{a: array<string, mixed>, b: array<string, mixed>, puntaje: float, evidencia: list<string>, estado: string}>
     */
    public static function cruzar(iterable $a, IndiceCruce $indice, string $idioma, OpcionesCruce $opciones, int &$omitidosA = 0): array
    {
        $candidatos = [];
        foreach ($a as $perfil) {
            if (!self::fallecido($perfil)) {
                $omitidosA++;
                continue;
            }
            $x = self::ficha($perfil, $idioma);
            // Candidatas: las de B cuyo nombre de pila está en el nombre de A («María de la Cruz» no es «José
            // de la Cruz»); además el de A debe estar en el nombre de ellas (se comprueba abajo).
            $candidatasB = [];
            foreach ($x['palabras'] as $palabra) {
                foreach ($indice->porPrimero[$palabra] ?? [] as $i) {
                    $candidatasB[$i] = true;
                }
            }
            foreach (array_keys($candidatasB) as $i) {
                $y = $indice->fichas[$i];
                if (!in_array($x['primero'], $y['palabras'], true)) {
                    continue;
                }
                $cuantas = count(array_intersect($x['palabras'], $y['palabras']));
                if ($cuantas < $opciones->palabrasMinimas) {
                    continue;
                }
                $union = count($x['palabras']) + count($y['palabras']) - $cuantas;
                $puntaje = $cuantas / $union;
                $evidencia = [$cuantas . ' palabras del nombre coinciden (plegado fonético)'];
                if ($x['anio'] !== null && $y['anio'] !== null) {
                    $diferencia = abs($x['anio'] - $y['anio']);
                    if ($diferencia > $opciones->maxAnios) {
                        continue;
                    }
                    if ($diferencia <= 1) {
                        $puntaje += 0.2;
                        $evidencia[] = 'nacimientos con un año de diferencia o menos';
                    }
                }
                if (array_intersect($x['lugares'], $y['lugares']) !== []) {
                    $puntaje += 0.1;
                    $evidencia[] = 'comparten una palabra de lugar';
                }
                if (array_intersect($x['parientes'], $y['parientes']) !== []) {
                    $puntaje += 0.15;
                    $evidencia[] = 'comparten un pariente por nombre';
                }
                if ($puntaje < $opciones->puntajeMinimo) {
                    continue;
                }
                $candidatos[] = [
                    'a' => self::referencia($perfil),
                    'b' => $y['ref'],
                    'puntaje' => round(min($puntaje, 1.0), 2),
                    'evidencia' => $evidencia,
                    'estado' => 'candidato: requiere revisión humana, no es una fusión',
                ];
            }
        }
        usort($candidatos, static fn (array $p, array $q): int => $q['puntaje'] <=> $p['puntaje']);

        return $candidatos;
    }

    /**
     * @param array<string, mixed> $perfil
     */
    private static function fallecido(array $perfil): bool
    {
        return ($perfil['posiblemente_vivo'] ?? true) === false && ($perfil['muerte'] ?? null) !== null;
    }

    /**
     * @param array<string, mixed> $p
     * @return array{palabras: list<string>, primero: ?string, anio: ?int, lugares: list<string>, parientes: list<string>}
     */
    private static function ficha(array $p, string $idioma): array
    {
        $anio = null;
        if (is_string($p['nacimiento'] ?? null) && preg_match('/^(\d{4})/', $p['nacimiento'], $m) === 1) {
            $anio = (int) $m[1];
        }
        $palabras = self::plegar(is_string($p['nombre'] ?? null) ? $p['nombre'] : '', $idioma);

        // Parientes con nombre: «padre|palabras plegadas del nombre». Sin nombre (solo un identificador) no se compara.
        $parientes = [];
        $lista = $p['parientes'] ?? null;
        foreach (is_array($lista) ? $lista : [] as $pariente) {
            $nombre = Json::texto($pariente, 'nombre');
            $relacion = Json::texto($pariente, 'relacion');
            if ($nombre !== null && $relacion !== null && ($plegado = self::plegar($nombre, $idioma)) !== []) {
                $parientes[] = $relacion . '|' . implode(' ', $plegado);
            }
        }

        return [
            'palabras' => $palabras,
            'primero' => $palabras[0] ?? null,
            'anio' => $anio,
            'lugares' => self::lugarEspecifico(is_string($p['lugar_nacimiento'] ?? null) ? $p['lugar_nacimiento'] : '', $idioma),
            'parientes' => $parientes,
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
