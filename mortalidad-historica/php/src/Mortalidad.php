<?php

declare(strict_types=1);

namespace MortalidadHistorica;

use InvalidArgumentException;
use RuntimeException;

/**
 * Port a PHP de consultas.py: causas de defunción históricas, epidemias, registro civil, esperanza de vida y
 * muertes en Londres, leídas de datos/*.csv. Devuelve las mismas respuestas que Python: lo comprueba
 * pruebas/ejecutar.php con datos/vectores.json, que genera generar_tabla.py con consultas.py.
 *
 * Ninguna función recibe una persona, una ficha ni un origen: trabajan con términos, años y regiones. Las que
 * hablan de causas devuelven correspondencias posibles de un **término** con su confianza, no diagnósticos.
 *
 * Necesita las extensiones mbstring e intl (para plegar mayúsculas y quitar tildes). Una instancia lee cada CSV
 * una vez y lo guarda; como las consultas de Python, lanza InvalidArgumentException ante una entrada no válida
 * (TypeError si el tipo es otro, con strict_types).
 */
final class Mortalidad
{
    public const IDIOMAS = [
        'spa', 'eng', 'por', 'fra', 'ita', 'deu', 'lat', 'nld', 'swe', 'dan',
        'pol', 'rus', 'ukr', 'ces', 'ell', 'tur', 'ara', 'heb', 'zho', 'jpn',
    ];

    public const REGIONES = ['África', 'América', 'Asia', 'Europa', 'Oceanía'];

    public const SEXOS = ['M', 'F', 'T'];

    private const ORDEN_CONFIANZA = ['alta' => 0, 'media' => 1, 'baja' => 2];

    /** @var array<string, list<array<string, string>>> */
    private array $csv = [];

    /** @var array<string, list<array{int, int, float, float|null}>>|null [desde, hasta, esperanza, supervivencia] por «país|sexo|edad» */
    private ?array $periodosDeEsperanza = null;

    /** @var array<int, array{int, int}>|null [totales, viruela] por año */
    private ?array $muertesDeLondres = null;

    private string $carpetaDeDatos;

    /** @param string|null $carpetaDeDatos Carpeta con los CSV; por omisión, la datos/ del proyecto. */
    public function __construct(?string $carpetaDeDatos = null)
    {
        $this->carpetaDeDatos = $carpetaDeDatos ?? __DIR__ . '/../../datos';
        if (!extension_loaded('intl') || !extension_loaded('mbstring')) {
            throw new RuntimeException('Mortalidad necesita las extensiones intl y mbstring.');
        }
    }

    /**
     * Forma para comparar términos: sin tildes ni diacríticos, en minúsculas y con espacios simples. Es la
     * misma que `normalizar` de comun.py.
     */
    public static function normalizar(string $texto): string
    {
        $plegado = mb_convert_case($texto, MB_CASE_FOLD, 'UTF-8');
        $descompuesto = \Normalizer::normalize($plegado, \Normalizer::FORM_D);
        if ($descompuesto === false) {
            throw new InvalidArgumentException('El texto no es UTF-8 válido.');
        }
        $sinMarcas = preg_replace('/\p{Mn}/u', '', $descompuesto);
        $compuesto = \Normalizer::normalize($sinMarcas ?? '', \Normalizer::FORM_C);
        $palabras = preg_split('/\s+/u', (string) $compuesto, -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', $palabras === false ? [] : $palabras);
    }

    /**
     * Causas modernas a las que puede corresponder un término histórico, de más a menos segura. Cada resultado
     * tiene `causa_id`, `nombre`, `cie10` (lista), `confianza` y `nota`. Lista vacía si el término no está.
     *
     * @return list<array{causa_id: string, nombre: string, cie10: list<string>, confianza: string, nota: string}>
     */
    public function causaModerna(string $termino, string $idioma): array
    {
        if (!in_array($idioma, self::IDIOMAS, true)) {
            throw new InvalidArgumentException("idioma «{$idioma}»: debe ser un código ISO 639-3 de " . implode(', ', self::IDIOMAS));
        }
        $modernas = [];
        foreach ($this->filas('causas_modernas') as $c) {
            $modernas[$c['id']] = $c;
        }
        $clave = self::normalizar($termino);
        $encontradas = [];
        foreach ($this->filas('causas_historicas') as $f) {
            if ($f['idioma'] === $idioma && self::normalizar($f['termino']) === $clave) {
                $m = $modernas[$f['causa_id']];
                $encontradas[] = [
                    'causa_id' => $f['causa_id'],
                    'nombre' => $m['nombre_spa'],
                    'cie10' => explode(';', $m['cie10']),
                    'confianza' => $f['confianza'],
                    'nota' => $f['nota'],
                ];
            }
        }
        usort($encontradas, fn (array $a, array $b): int => [self::ORDEN_CONFIANZA[$a['confianza']], $a['nombre']] <=> [self::ORDEN_CONFIANZA[$b['confianza']], $b['nombre']]);

        return $encontradas;
    }

    /** Región (África, América, Asia, Europa u Oceanía) de un país por su ISO alfa-2, o null si no está. */
    public function regionDe(string $pais): ?string
    {
        foreach ($this->filas('paises') as $f) {
            if ($f['codigo'] === $pais) {
                return $f['region'];
            }
        }

        return null;
    }

    /**
     * Epidemias que cubren un año, filtradas por región y/o por país. Una epidemia sin `hasta` sigue abierta.
     * Con `$pais`, salen las que lo nombran en `paises` y las que no nombran ningún país pero cubren su región.
     * Cada resultado es la fila del CSV, con `regiones` y `paises` como listas.
     *
     * @return list<array<string, mixed>>
     */
    public function epidemiasEn(int $anio, ?string $region = null, ?string $pais = null): array
    {
        if ($region !== null && !in_array($region, self::REGIONES, true)) {
            throw new InvalidArgumentException("región «{$region}»: debe ser una de " . implode(', ', self::REGIONES));
        }
        $regionDelPais = null;
        if ($pais !== null) {
            $regionDelPais = $this->regionDe($pais);
            if ($regionDelPais === null) {
                throw new InvalidArgumentException("país «{$pais}»: no está en paises.csv (ISO alfa-2 en mayúsculas)");
            }
        }
        $resultado = [];
        foreach ($this->filas('epidemias') as $f) {
            $desde = (int) $f['desde'];
            $hasta = $f['hasta'] === '' ? null : (int) $f['hasta'];
            $regiones = explode(';', $f['regiones']);
            $paises = array_values(array_filter(explode(';', $f['paises']), fn (string $c): bool => $c !== ''));
            if (!($desde <= $anio && ($hasta === null || $anio <= $hasta))) {
                continue;
            }
            if ($region !== null && !in_array($region, $regiones, true)) {
                continue;
            }
            if ($pais !== null && !(in_array($pais, $paises, true) || ($paises === [] && in_array($regionDelPais, $regiones, true)))) {
                continue;
            }
            $resultado[] = ['regiones' => $regiones, 'paises' => $paises] + $f;
        }
        usort($resultado, fn (array $a, array $b): int => [(int) $a['desde'], $a['id']] <=> [(int) $b['desde'], $b['id']]);

        return $resultado;
    }

    /**
     * Hitos de registro (civil o parroquial) de un país (ISO alfa-2), del más antiguo al más reciente.
     *
     * @return list<array<string, string>>
     */
    public function hitosDe(string $codigo): array
    {
        $hitos = array_values(array_filter($this->filas('hitos_registro'), fn (array $f): bool => $f['codigo'] === $codigo));
        usort($hitos, fn (array $a, array $b): int => (int) $a['desde'] <=> (int) $b['desde']);

        return $hitos;
    }

    /**
     * Años que le quedaban por vivir, en promedio, a quien tenía esa edad en ese país y ese año. Es una cifra de
     * período (la mortalidad de la década que contiene `$anio`) de toda la población, no de una generación ni de
     * una persona. Null si no hay dato: país sin serie, año fuera de 1800-2009, o una edad que no está (hoy solo
     * 0 y 5; 5 solo para ambos sexos). No interpola entre edades.
     */
    public function esperanzaRestante(string $codigo, int $anio, int $edad = 0, string $sexo = 'T'): ?float
    {
        return $this->periodoQueCubre($codigo, $anio, $edad, $sexo)[0] ?? null;
    }

    /** Probabilidad de llegar con vida a esa edad (de 0 a 1), en la década que contiene `$anio`; hoy solo la edad 5. */
    public function supervivencia(string $codigo, int $anio, int $edad = 5, string $sexo = 'T'): ?float
    {
        return $this->periodoQueCubre($codigo, $anio, $edad, $sexo)[1] ?? null;
    }

    /**
     * Muertes en Londres ese año (1629-1902), de las Bills of Mortality y los registros que las siguieron:
     * ['totales' => int, 'viruela' => int], o null si el año falta (hay huecos). Es la ciudad, no el país.
     *
     * @return array{totales: int, viruela: int}|null
     */
    public function muertesLondres(int $anio): ?array
    {
        $m = $this->londres()[$anio] ?? null;

        return $m === null ? null : ['totales' => $m[0], 'viruela' => $m[1]];
    }

    /**
     * Cuántas veces las muertes totales de ese año superan a las de sus vecinos (mediana de ±`$ventana` años).
     * Null si el año falta o hay menos de 4 vecinos con dato.
     */
    public function excesoDeMuertesLondres(int $anio, int $ventana = 5): ?float
    {
        $serie = $this->londres();
        if (!isset($serie[$anio])) {
            return null;
        }
        $vecinos = [];
        for ($a = $anio - $ventana; $a <= $anio + $ventana; $a++) {
            if ($a !== $anio && isset($serie[$a])) {
                $vecinos[] = $serie[$a][0];
            }
        }
        if (count($vecinos) < 4) {
            return null;
        }
        sort($vecinos);
        $mitad = intdiv(count($vecinos), 2);
        $mediana = count($vecinos) % 2 === 1 ? $vecinos[$mitad] : ($vecinos[$mitad - 1] + $vecinos[$mitad]) / 2;

        return $serie[$anio][0] / $mediana;
    }

    /** @return array{float, float|null}|null */
    private function periodoQueCubre(string $codigo, int $anio, int $edad, string $sexo): ?array
    {
        if (!in_array($sexo, self::SEXOS, true)) {
            throw new InvalidArgumentException("sexo «{$sexo}»: debe ser uno de " . implode(', ', self::SEXOS));
        }
        if ($this->periodosDeEsperanza === null) {
            $this->periodosDeEsperanza = [];
            foreach ($this->filas('esperanza_vida') as $f) {
                $clave = $f['codigo'] . '|' . $f['sexo'] . '|' . (int) $f['edad'];
                $this->periodosDeEsperanza[$clave][] = [
                    (int) $f['desde'], (int) $f['hasta'], (float) $f['esperanza_restante'],
                    $f['supervivencia_hasta_edad'] === '' ? null : (float) $f['supervivencia_hasta_edad'],
                ];
            }
        }
        foreach ($this->periodosDeEsperanza["$codigo|$sexo|$edad"] ?? [] as [$desde, $hasta, $esperanza, $supervivencia]) {
            if ($desde <= $anio && $anio <= $hasta) {
                return [$esperanza, $supervivencia];
            }
        }

        return null;
    }

    /** @return array<int, array{0: int, 1: int}> */
    private function londres(): array
    {
        if ($this->muertesDeLondres === null) {
            $this->muertesDeLondres = [];
            foreach ($this->filas('londres_muertes') as $f) {
                $this->muertesDeLondres[(int) $f['anio']] = [(int) $f['muertes_totales'], (int) $f['muertes_viruela']];
            }
        }

        return $this->muertesDeLondres;
    }

    /** @return list<array<string, string>> Las filas de datos/<nombre>.csv, como arrays asociativos. */
    private function filas(string $nombre): array
    {
        if (isset($this->csv[$nombre])) {
            return $this->csv[$nombre];
        }
        $ruta = "{$this->carpetaDeDatos}/$nombre.csv";
        $f = @fopen($ruta, 'rb');
        if ($f === false) {
            throw new RuntimeException("No se pudo abrir $ruta");
        }
        $filas = [];
        $columnas = fgetcsv($f, 0, ',', '"', '');
        if ($columnas === false || in_array(null, $columnas, true)) {
            throw new RuntimeException("$ruta no tiene una cabecera válida");
        }
        while (($campos = fgetcsv($f, 0, ',', '"', '')) !== false) {
            if ($campos === [null]) {
                continue; // línea en blanco
            }
            $filas[] = array_combine($columnas, array_map(fn (?string $c): string => (string) $c, $campos));
        }
        fclose($f);

        return $this->csv[$nombre] = $filas;
    }
}
