<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use RuntimeException;

/**
 * Lee un volcado de Wikidata (`latest-all.json` o `.json.gz`, un archivo local) línea a
 * línea, sin cargarlo en memoria, y entrega las personas que constan como fallecidas.
 * Los datos de Wikidata son CC0. No toca ningún sitio web: usa el volcado que tú bajaste.
 *
 * Los lugares y los parientes salen como identificadores (`Q2887`): el volcado no trae
 * sus nombres en la misma línea, y resolverlos pide un segundo recorrido.
 */
final class Wikidata
{
    private const PARENTESCOS = ['P22' => 'padre', 'P25' => 'madre', 'P26' => 'cónyuge', 'P40' => 'hijo'];

    /**
     * @param callable(Perfil): void $cada
     * @param ?string $pais identificador del país de ciudadanía (`Q298` es Chile), o null para todos
     * `leidas` son las líneas con fecha de muerte; `decodificadas`, las que pasaron el prefiltro de
     * texto (humano y, si se pidió, país) y costaron un `json_decode`; `aceptadas`, las personas entregadas.
     *
     * @return array{leidas: int, decodificadas: int, aceptadas: int}
     */
    public static function recorrer(string $ruta, callable $cada, ?string $pais = null, ?int $maximo = null, ?string $capturado = null): array
    {
        $abrir = str_ends_with($ruta, '.gz') ? 'compress.zlib://' . $ruta : $ruta;
        $f = is_file($ruta) ? @fopen($abrir, 'rb') : false;
        if ($f === false) {
            throw new RuntimeException("No se pudo abrir $ruta");
        }
        $leidas = 0;
        $decodificadas = 0;
        $aceptadas = 0;
        // Prefiltros de texto: nunca descartan a alguien que luego `persona()` aceptaría (esperan el
        // identificador, con o sin espacios), solo evitan decodificar lo que seguro no sirve.
        $humano = '/"id"\s*:\s*"Q5"/';
        $delPais = $pais === null ? null : '/"id"\s*:\s*"' . preg_quote($pais, '/') . '"/';
        while (($linea = fgets($f)) !== false) {
            $linea = rtrim($linea, ",\r\n ");
            // Casi ninguna línea es de una persona fallecida: se descarta antes de decodificar.
            if ($linea === '' || $linea === '[' || $linea === ']' || !str_contains($linea, '"P570"')) {
                continue;
            }
            $leidas++;
            if (preg_match($humano, $linea) !== 1 || ($delPais !== null && preg_match($delPais, $linea) !== 1)) {
                continue;
            }
            $decodificadas++;
            $entidad = json_decode($linea, true);
            $perfil = is_array($entidad) ? self::persona($entidad, $pais, $capturado) : null;
            if ($perfil === null) {
                continue;
            }
            $cada($perfil);
            $aceptadas++;
            if ($maximo !== null && $aceptadas >= $maximo) {
                break;
            }
        }
        fclose($f);
        return ['leidas' => $leidas, 'decodificadas' => $decodificadas, 'aceptadas' => $aceptadas];
    }

    /**
     * @param array<mixed> $e una entidad del volcado
     */
    public static function persona(array $e, ?string $pais = null, ?string $capturado = null): ?Perfil
    {
        $id = $e['id'] ?? null;
        if (!is_string($id) || !in_array('Q5', self::ids($e, 'P31'), true)) {
            return null;
        }
        $muerte = self::fecha($e, 'P570');
        if ($muerte === null) {
            return null;
        }
        if ($pais !== null && !in_array($pais, self::ids($e, 'P27'), true)) {
            return null;
        }
        $parientes = [];
        foreach (self::PARENTESCOS as $propiedad => $relacion) {
            foreach (self::ids($e, $propiedad) as $pariente) {
                $parientes[] = ['relacion' => $relacion, 'nombre' => null, 'id' => $pariente];
            }
        }
        $lugarNacimiento = self::ids($e, 'P19')[0] ?? null;
        $lugarMuerte = self::ids($e, 'P20')[0] ?? null;
        $avisos = ['lugares y parientes son identificadores de Wikidata, sin resolver'];
        if ($capturado === null) {
            $avisos[] = 'sin fecha de captura: anótala al citar la fuente (la del volcado)';
        }

        return new Perfil(
            'wikidata',
            'https://www.wikidata.org/wiki/' . $id,
            $id,
            self::texto($e, 'labels'),
            null,
            null,
            self::texto($e, 'descriptions'),
            $capturado,
            $avisos,
            self::fecha($e, 'P569'),
            $muerte,
            $lugarNacimiento,
            $lugarMuerte,
            $parientes,
        );
    }

    /**
     * @param array<mixed> $e
     * @return list<string>
     */
    private static function ids(array $e, string $propiedad): array
    {
        $ids = [];
        foreach (self::declaraciones($e, $propiedad) as $d) {
            $id = Json::texto($d, 'mainsnak', 'datavalue', 'value', 'id');
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Fecha del primer valor que no esté marcado como obsoleto, con la precisión que trae
     * Wikidata: día (11), mes (10) o año (9 o menos).
     *
     * @param array<mixed> $e
     */
    private static function fecha(array $e, string $propiedad): ?string
    {
        foreach (self::declaraciones($e, $propiedad) as $d) {
            $hora = Json::texto($d, 'mainsnak', 'datavalue', 'value', 'time');
            if ($hora === null || preg_match('/^([+-])(\d{1,})-(\d{2})-(\d{2})T/', $hora, $m) !== 1) {
                continue;
            }
            $p = Json::en($d, 'mainsnak', 'datavalue', 'value', 'precision');
            $precision = is_int($p) ? $p : 9;
            $anio = ($m[1] === '-' ? '-' : '') . str_pad(ltrim($m[2], '0') === '' ? '0' : ltrim($m[2], '0'), 4, '0', STR_PAD_LEFT);
            return match (true) {
                $precision >= 11 && $m[3] !== '00' && $m[4] !== '00' => "$anio-$m[3]-$m[4]",
                $precision >= 10 && $m[3] !== '00' => "$anio-$m[3]",
                default => $anio,
            };
        }
        return null;
    }

    /**
     * @param array<mixed> $e
     * @return list<array<mixed>>
     */
    private static function declaraciones(array $e, string $propiedad): array
    {
        $salida = [];
        $lista = Json::en($e, 'claims', $propiedad);
        if (is_array($lista)) {
            foreach ($lista as $d) {
                if (is_array($d) && Json::texto($d, 'rank') !== 'deprecated') {
                    $salida[] = $d;
                }
            }
        }
        return $salida;
    }

    /**
     * Etiqueta o descripción: en español, si no en inglés, si no la primera que haya.
     *
     * @param array<mixed> $e
     */
    private static function texto(array $e, string $campo): ?string
    {
        $m = Json::en($e, $campo);
        if (!is_array($m)) {
            return null;
        }
        foreach (['es', 'en'] as $idioma) {
            $texto = Json::texto($m, $idioma, 'value');
            if ($texto !== null) {
                return $texto;
            }
        }
        return Json::texto(reset($m), 'value');
    }
}
