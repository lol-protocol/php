<?php

declare(strict_types=1);

namespace SignosZodiacales;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Port a PHP de zodiaco.py: el signo occidental de una fecha y el animal chino de un año, desde
 * datos/signos.json. Las funciones devuelven los arrays de los datos tal cual y lanzan excepción ante
 * una entrada no válida (InvalidArgumentException; TypeError si el tipo es otro, con strict_types).
 *
 * Las respuestas coinciden con las de Python: lo comprueba pruebas/ejecutar.php con datos/vectores.json.
 *
 * Las fechas son DateTimeInterface y se toman por su día en su propio huso (la hora no cuenta), salvo en
 * animalChinoDeInstante(), que las pasa a la hora de China. PHP no distingue una fecha «sin huso» de otra
 * con huso, como sí hace Python con date y datetime; por eso son dos funciones.
 */
final class Zodiaco
{
    public const SISTEMAS = ['tropical', 'sideral'];

    /** Orden caldeo de los siete astros: regentes de los 36 decanatos, desde Marte en el primero de Aries. */
    public const ORDEN_CALDEO = ['marte', 'sol', 'venus', 'mercurio', 'luna', 'saturno', 'jupiter'];

    public const AFINIDADES = ['alta', 'media', 'neutra', 'baja'];

    /** 1984 fue 甲子 (madera, rata): el ciclo de 60 años empieza en el año 4 de la era común. */
    private const ANIO_BASE = 4;

    private const COMPLEMENTARIOS = [['fuego', 'aire'], ['tierra', 'agua']];

    /** @var array<string, mixed>|null */
    private static ?array $datos = null;

    /** @var array<int, string>|null */
    private static ?array $anioNuevo = null;

    /** @return array<string, mixed> Lee datos/signos.json una sola vez. */
    public static function cargarDatos(): array
    {
        return self::$datos ??= self::leerJson(__DIR__ . '/../../datos/signos.json');
    }

    /** @return array<int, string> {año: fecha ISO del Año Nuevo lunar}, de 1900 a 2099. */
    public static function fechasAnioNuevo(): array
    {
        if (self::$anioNuevo === null) {
            self::$anioNuevo = [];
            foreach (self::leerJson(__DIR__ . '/../../datos/anio-nuevo-lunar.json')['fechas'] as $anio => $fecha) {
                self::$anioNuevo[(int) $anio] = $fecha;
            }
        }
        return self::$anioNuevo;
    }

    /** La versión de los datos (X.Y.Z, la de CHANGELOG.md). */
    public static function version(): string
    {
        return self::cargarDatos()['version'];
    }

    /**
     * El estado de revisión de las traducciones de un idioma.
     *
     * @return array<string, string> ['estado' => 'sin_revisar'] o ['estado' => 'revisado', 'por' => …, 'fecha' => …]
     */
    public static function revision(string $idioma): array
    {
        $revision = self::cargarDatos()['revision'];
        if (!isset($revision[$idioma])) {
            throw new InvalidArgumentException("idioma desconocido: '$idioma'");
        }
        return $revision[$idioma];
    }

    /**
     * El signo occidental de una fecha. Sistema: «tropical» (el habitual) o «sideral» (astrología védica,
     * unos 24 días más tarde). Las fechas límite son aproximadas: varían un día según el año y la hora.
     *
     * @return array<string, mixed>
     */
    public static function signoOccidental(DateTimeInterface $fecha, string $sistema = 'tropical'): array
    {
        self::sistemaValido($sistema);
        $clave = [(int) $fecha->format('n'), (int) $fecha->format('j')];
        foreach (self::cargarDatos()['signos'] as $signo) {
            if ($signo['tipo'] === 'occidental' && self::enRango($clave, $signo[$sistema]['desde'], $signo[$sistema]['hasta'])) {
                return $signo;
            }
        }
        throw new LogicException(sprintf('ningún signo %s cubre el %02d/%02d', $sistema, $clave[1], $clave[0]));
    }

    /**
     * Los dos signos entre los que cae una fecha si está a $margen días o menos de un cambio de signo
     * ([anterior, siguiente]), o null. Sirve para no afirmar un signo que puede estar mal.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    public static function cuspide(DateTimeInterface $fecha, string $sistema = 'tropical', int $margen = 1): ?array
    {
        if ($margen < 0) {
            throw new InvalidArgumentException("margen debe ser un entero mayor o igual que 0, no $margen");
        }
        $dia = self::dia($fecha);
        $propio = self::signoOccidental($dia, $sistema);
        for ($d = 1; $d <= $margen; $d++) {
            $antes = self::signoOccidental($dia->modify("-$d days"), $sistema);
            if ($antes['id'] !== $propio['id']) {
                return [$antes, $propio];
            }
            $despues = self::signoOccidental($dia->modify("+$d days"), $sistema);
            if ($despues['id'] !== $propio['id']) {
                return [$propio, $despues];
            }
        }
        return null;
    }

    public static function esCuspide(DateTimeInterface $fecha, string $sistema = 'tropical', int $margen = 1): bool
    {
        return self::cuspide($fecha, $sistema, $margen) !== null;
    }

    /**
     * Como animalChino(), desde una fecha del calendario gregoriano (1900–2099): una fecha anterior al Año
     * Nuevo lunar de su año pertenece al año lunar anterior. Se toma el día de la fecha en su propio huso;
     * para un instante exacto, animalChinoDeInstante().
     *
     * @return array<string, mixed> Lo de animalChino() más «anio_lunar».
     */
    public static function animalChinoDeFecha(DateTimeInterface $fecha): array
    {
        $tabla = self::fechasAnioNuevo();
        $anio = (int) $fecha->format('Y');
        if (!isset($tabla[$anio])) {
            throw new InvalidArgumentException(sprintf(
                'solo hay fechas del Año Nuevo lunar de %d a %d, no de %d',
                min(array_keys($tabla)),
                max(array_keys($tabla)),
                $anio,
            ));
        }
        $anioLunar = self::dia($fecha)->format('Y-m-d') >= $tabla[$anio] ? $anio : $anio - 1;
        return self::animalChino($anioLunar) + ['anio_lunar' => $anioLunar];
    }

    /**
     * Como animalChinoDeFecha(), pero pasando antes el instante a la hora de China (UTC+8), que es la de las
     * fechas del Año Nuevo: las 20:00 UTC del 16/02/2026 ya son el 17/02 en China, año del Caballo.
     *
     * @return array<string, mixed>
     */
    public static function animalChinoDeInstante(DateTimeInterface $instante): array
    {
        $enChina = DateTimeImmutable::createFromInterface($instante)->setTimezone(new DateTimeZone('+08:00'));
        return self::animalChinoDeFecha($enChina);
    }

    /**
     * El animal, el tronco celeste y el elemento de un año del calendario chino (el año lunar, que empieza con
     * el Año Nuevo chino). Devuelve «signo», «tronco», «elemento_del_anio» (el del ciclo de 60, no el fijo del
     * animal) y «polaridad» del año.
     *
     * @return array{signo: array<string, mixed>, tronco: array<string, string>, elemento_del_anio: string, polaridad: string}
     */
    public static function animalChino(int $anioLunar): array
    {
        $datos = self::cargarDatos();
        $chinos = array_values(array_filter($datos['signos'], fn (array $s): bool => $s['tipo'] === 'chino'));
        $tronco = $datos['troncos'][self::modulo($anioLunar - self::ANIO_BASE, 10)];
        return [
            'signo' => $chinos[self::modulo($anioLunar - self::ANIO_BASE, 12)],
            'tronco' => $tronco,
            'elemento_del_anio' => $tronco['elemento'],
            'polaridad' => $tronco['polaridad'],
        ];
    }

    /** El nombre del año en el ciclo de 60 en un idioma: «Caballo de Fuego», «Fire Horse», «火马». */
    public static function nombreDelAnio(int $anioLunar, string $idioma): string
    {
        $datos = self::cargarDatos();
        if (!isset($datos['nombres_del_ciclo'][$idioma])) {
            throw new InvalidArgumentException("idioma desconocido: '$idioma'");
        }
        $regla = $datos['nombres_del_ciclo'][$idioma];
        $anio = self::animalChino($anioLunar);
        $elemento = $regla['elementos'][$anio['elemento_del_anio']] ?? $datos['terminos'][$anio['elemento_del_anio']][$idioma];
        $animal = $anio['signo']['nombres'][$idioma];
        $nombre = strtr($regla['plantilla'], [
            '{elemento}' => $elemento,
            '{animal}' => $animal,
            '{animal_minusculas}' => mb_strtolower($animal),
        ]);
        if (!empty($regla['elision'])) {
            // «Rat de Eau» → «Rat d'Eau», «Topo di Acqua» → «Topo d'Acqua».
            $nombre = preg_replace('/\b(?:de|di) (?=[AEIOUÉÈÊaeiouéèê])/u', "d'", $nombre);
        }
        return $nombre;
    }

    /**
     * La afinidad tradicional entre dos signos del mismo zodiaco: «relaciones» (mismo_signo, mismo_elemento,
     * elementos_complementarios, opuestos, cuadratura; o, en el chino, mismo_signo, trino, amigos_secretos,
     * choque) y «afinidad» (alta, media, neutra o baja). Es lo que dice la tradición; no tiene respaldo científico.
     *
     * @return array{relaciones: list<string>, afinidad: string}
     */
    public static function compatibilidad(string $idA, string $idB): array
    {
        $porId = [];
        foreach (self::cargarDatos()['signos'] as $signo) {
            $porId[$signo['id']] = $signo;
        }
        foreach ([$idA, $idB] as $id) {
            if (!isset($porId[$id])) {
                throw new InvalidArgumentException("signo desconocido: '$id'");
            }
        }
        $a = $porId[$idA];
        $b = $porId[$idB];
        if ($a['tipo'] !== $b['tipo']) {
            throw new InvalidArgumentException('solo se comparan signos del mismo zodiaco (occidental con occidental, chino con chino)');
        }
        $delTipo = array_keys(array_filter($porId, fn (array $s): bool => $s['tipo'] === $a['tipo']));
        $i = (int) array_search($idA, $delTipo, true);
        $j = (int) array_search($idB, $delTipo, true);
        $distancia = min(self::modulo($i - $j, 12), self::modulo($j - $i, 12));
        $relaciones = [];
        if ($distancia === 0) {
            $relaciones[] = 'mismo_signo';
        }
        if ($a['tipo'] === 'occidental') {
            if ($a['elemento'] === $b['elemento']) {
                $relaciones[] = 'mismo_elemento';
            }
            if (self::sonComplementarios($a['elemento'], $b['elemento'])) {
                $relaciones[] = 'elementos_complementarios';
            }
            if ($distancia === 6) {
                $relaciones[] = 'opuestos';
            }
            if ($distancia === 3) {
                $relaciones[] = 'cuadratura';
            }
            $afinidad = match (true) {
                in_array('opuestos', $relaciones, true) => 'media',
                in_array('cuadratura', $relaciones, true) => 'baja',
                (bool) array_intersect(['mismo_elemento', 'elementos_complementarios'], $relaciones) => 'alta',
                default => 'neutra',
            };
        } else {
            if ($i % 4 === $j % 4) {
                $relaciones[] = 'trino';
            }
            if (($i + $j) % 12 === 1) {
                $relaciones[] = 'amigos_secretos';
            }
            if ($distancia === 6) {
                $relaciones[] = 'choque';
            }
            $afinidad = match (true) {
                in_array('choque', $relaciones, true) => 'baja',
                (bool) array_intersect(['trino', 'amigos_secretos'], $relaciones) => 'alta',
                default => 'neutra',
            };
        }
        return ['relaciones' => $relaciones, 'afinidad' => $afinidad];
    }

    /**
     * El decanato de una fecha: cada signo se parte en tres tramos de unos 10 días. «signo», «numero» (1 a 3),
     * «regente» (el astro en el orden caldeo; clave de «terminos») y «signo_regente» (el del mismo elemento que
     * lo rige por triplicidad: Aries, Leo, Sagitario).
     *
     * @return array{signo: array<string, mixed>, numero: int, regente: string, signo_regente: array<string, mixed>}
     */
    public static function decanato(DateTimeInterface $fecha, string $sistema = 'tropical'): array
    {
        $signo = self::signoOccidental($fecha, $sistema);
        $dia = self::dia($fecha);
        $numero = min(intdiv(self::diasEntre(self::inicioDelSigno($dia, $signo, $sistema), $dia), 10), 2) + 1;
        $occidentales = self::occidentales();
        $indice = (int) array_search($signo['id'], array_column($occidentales, 'id'), true);
        return [
            'signo' => $signo,
            'numero' => $numero,
            'regente' => self::regentesDeDecanatos($signo['id'])[$numero - 1],
            'signo_regente' => $occidentales[($indice + 4 * ($numero - 1)) % 12],
        ];
    }

    /** @return list<string> Los astros regentes de los tres decanatos de un signo, en el orden caldeo. */
    public static function regentesDeDecanatos(string $idSigno): array
    {
        $indice = array_search($idSigno, array_column(self::occidentales(), 'id'), true);
        if ($indice === false) {
            throw new InvalidArgumentException("signo occidental desconocido: '$idSigno'");
        }
        return array_map(fn (int $n): string => self::ORDEN_CALDEO[($indice * 3 + $n) % 7], [0, 1, 2]);
    }

    /**
     * Los tres tramos de un signo como [[mes, día] desde, [mes, día] hasta] en un año dado.
     *
     * @param array<string, mixed> $signo
     * @return list<array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}>
     */
    public static function tramosDeDecanatos(array $signo, string $sistema = 'tropical', int $anio = 2025): array
    {
        self::sistemaValido($sistema);
        $desde = self::fecha($anio, $signo[$sistema]['desde'][0], $signo[$sistema]['desde'][1]);
        $hasta = self::fecha($anio, $signo[$sistema]['hasta'][0], $signo[$sistema]['hasta'][1]);
        if ($hasta < $desde) { // cruza el fin de año (Capricornio, y Sagitario en el sideral)
            $hasta = $hasta->modify('+1 year');
        }
        $cortes = [$desde, $desde->modify('+10 days'), $desde->modify('+20 days'), $hasta->modify('+1 day')];
        $tramos = [];
        for ($n = 0; $n < 3; $n++) {
            $fin = $cortes[$n + 1]->modify('-1 day');
            $tramos[] = [
                [(int) $cortes[$n]->format('n'), (int) $cortes[$n]->format('j')],
                [(int) $fin->format('n'), (int) $fin->format('j')],
            ];
        }
        return $tramos;
    }

    /** @return list<int> Los años lunares entre $desde y $hasta (inclusive) que corresponden a un animal. */
    public static function aniosDelAnimal(string $idAnimal, int $desde, int $hasta): array
    {
        $ids = array_column(array_filter(self::cargarDatos()['signos'], fn (array $s): bool => $s['tipo'] === 'chino'), 'id');
        $indice = array_search($idAnimal, array_values($ids), true);
        if ($indice === false) {
            throw new InvalidArgumentException("animal desconocido: '$idAnimal'");
        }
        $anios = [];
        for ($a = $desde; $a <= $hasta; $a++) {
            if (self::modulo($a - self::ANIO_BASE, 12) === $indice) {
                $anios[] = $a;
            }
        }
        return $anios;
    }

    // --- auxiliares ---

    /** @return array<string, mixed> */
    private static function leerJson(string $ruta): array
    {
        $texto = file_get_contents($ruta);
        if ($texto === false) {
            throw new RuntimeException("no puedo leer $ruta");
        }
        return json_decode($texto, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function sistemaValido(string $sistema): void
    {
        if (!in_array($sistema, self::SISTEMAS, true)) {
            throw new InvalidArgumentException('sistema debe ser uno de ' . implode(', ', self::SISTEMAS) . ", no '$sistema'");
        }
    }

    /** El módulo matemático (el resultado nunca es negativo), como el % de Python. */
    private static function modulo(int $n, int $m): int
    {
        return (($n % $m) + $m) % $m;
    }

    /**
     * Si [mes, día] cae entre $desde y $hasta, inclusive; el rango puede cruzar el fin de año.
     *
     * @param array{0: int, 1: int} $mesDia
     * @param array{0: int, 1: int} $desde
     * @param array{0: int, 1: int} $hasta
     */
    private static function enRango(array $mesDia, array $desde, array $hasta): bool
    {
        if ($desde <= $hasta) {
            return $desde <= $mesDia && $mesDia <= $hasta;
        }
        return $mesDia >= $desde || $mesDia <= $hasta;
    }

    /** Fuego con aire y tierra con agua: dos elementos distintos de un mismo par. */
    private static function sonComplementarios(string $a, string $b): bool
    {
        if ($a === $b) {
            return false;
        }
        foreach (self::COMPLEMENTARIOS as $par) {
            if (in_array($a, $par, true) && in_array($b, $par, true)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<array<string, mixed>> */
    private static function occidentales(): array
    {
        return array_values(array_filter(self::cargarDatos()['signos'], fn (array $s): bool => $s['tipo'] === 'occidental'));
    }

    /** Medianoche UTC del año, mes y día dados: sin huso ni horario de verano que cambien el día. */
    private static function fecha(int $anio, int $mes, int $dia): DateTimeImmutable
    {
        return (new DateTimeImmutable('today', new DateTimeZone('UTC')))->setDate($anio, $mes, $dia)->setTime(0, 0);
    }

    private static function dia(DateTimeInterface $fecha): DateTimeImmutable
    {
        return self::fecha((int) $fecha->format('Y'), (int) $fecha->format('n'), (int) $fecha->format('j'));
    }

    private static function diasEntre(DateTimeImmutable $desde, DateTimeImmutable $hasta): int
    {
        return intdiv($hasta->getTimestamp() - $desde->getTimestamp(), 86400);
    }

    /** La fecha en que empezó el tramo del signo que contiene a $fecha. */
    private static function inicioDelSigno(DateTimeImmutable $fecha, array $signo, string $sistema): DateTimeImmutable
    {
        [$mes, $dia] = $signo[$sistema]['desde'];
        $inicio = self::fecha((int) $fecha->format('Y'), $mes, $dia);
        return $inicio <= $fecha ? $inicio : self::fecha((int) $fecha->format('Y') - 1, $mes, $dia);
    }
}
