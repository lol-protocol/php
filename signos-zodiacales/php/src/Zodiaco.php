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

    /** Los 72 ángeles van de ocho en ocho en nueve coros, de los Serafines a los Ángeles. */
    public const CHOROS = ['serafines', 'querubines', 'tronos', 'dominaciones', 'virtudes', 'potestades', 'principados', 'arcangeles', 'angeles'];

    public const TIPOS_DE_ESPIRITU = ['angel', 'demonio'];

    public const RANGOS = ['rey', 'duque', 'principe', 'marques', 'conde', 'presidente', 'caballero'];

    public const SISTEMAS_DE_ARBOLES = ['celta', 'horoscopo', 'nakshatra'];

    /** Ayanamsa de Lahiri (la distancia entre el zodiaco tropical y el sideral): 23°51′11″ en J2000.0 y unos 50,29″ al año. */
    private const AYANAMSA_J2000 = 23.85306;
    private const AYANAMSA_POR_ANIO = 50.29 / 3600;

    /**
     * Términos principales de la longitud de la Luna (Meeus, «Astronomical Algorithms», cap. 47): [D, M, M', F,
     * coeficiente en millonésimas de grado]. D es la elongación media; M y M', las anomalías medias del Sol y de la
     * Luna; F, el argumento de latitud.
     */
    private const TERMINOS_LUNARES = [
        [0, 0, 1, 0, 6288774], [2, 0, -1, 0, 1274027], [2, 0, 0, 0, 658314], [0, 0, 2, 0, 213618],
        [0, 1, 0, 0, -185116], [0, 0, 0, 2, -114332], [2, 0, -2, 0, 58793], [2, -1, -1, 0, 57066],
        [2, 0, 1, 0, 53322], [2, -1, 0, 0, 45758], [0, 1, -1, 0, -40923], [1, 0, 0, 0, -34720],
        [0, 1, 1, 0, -30383], [2, 0, 0, -2, 15327], [0, 0, 1, 2, -12528], [0, 0, 1, -2, 10980],
        [4, 0, -1, 0, 10675], [0, 0, 3, 0, 10034], [4, 0, -2, 0, 8548], [2, 1, -1, 0, -7888],
        [2, 1, 0, 0, -6766], [1, 0, -1, 0, -5163], [1, 1, 0, 0, 4987], [2, -1, 1, 0, 4036],
        [2, 0, 2, 0, 3994], [4, 0, 0, 0, 3861], [2, 0, -3, 0, 3665], [0, 1, -2, 0, -2689],
        [2, 0, -1, 2, -2602], [2, -1, -2, 0, 2390], [1, 0, 1, 0, -2348], [2, -2, 0, 0, 2236],
    ];

    /** 1984 fue 甲子 (madera, rata): el ciclo de 60 años empieza en el año 4 de la era común. */
    private const ANIO_BASE = 4;

    private const COMPLEMENTARIOS = [['fuego', 'aire'], ['tierra', 'agua']];

    /** @var array<string, mixed>|null */
    private static ?array $datos = null;

    /** @var array<int, string>|null */
    private static ?array $anioNuevo = null;

    /** @var array<string, mixed>|null */
    private static ?array $espiritus = null;

    /** @var array<string, mixed>|null */
    private static ?array $arboles = null;

    /** @return array<string, mixed> Lee datos/signos.json una sola vez. */
    public static function cargarDatos(): array
    {
        return self::$datos ??= self::leerJson(__DIR__ . '/../../datos/signos.json');
    }

    /** @return array<string, mixed> Lee datos/espiritus.json una sola vez: los 72 ángeles y los 72 demonios. */
    public static function cargarEspiritus(): array
    {
        return self::$espiritus ??= self::leerJson(__DIR__ . '/../../datos/espiritus.json');
    }

    /** @return array<string, mixed> Lee datos/arboles.json una sola vez: el calendario celta, el horóscopo y los nakshatras. */
    public static function cargarArboles(): array
    {
        return self::$arboles ??= self::leerJson(__DIR__ . '/../../datos/arboles.json');
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

    // --- Los 72 ángeles del Shem ha-Mephorash y los 72 demonios de la Goetia ---
    // Cada uno se atribuye a un tramo de 5° del zodiaco tropical: seis por signo, desde Aries. Cada signo se parte
    // en seis tramos de fechas casi iguales (los primeros se llevan el día que sobre en los signos de 31 o 32
    // días). Un ángel y el demonio del mismo número comparten tramo.

    /**
     * Los seis tramos de un signo occidental como [[mes, día] desde, [mes, día] hasta] en un año dado. Aries
     * tiene los números 1 a 6, Tauro del 7 al 12… El año importa en Piscis (febrero tiene 28 o 29 días) y en
     * Capricornio, que empieza el 22/12 de ese año.
     *
     * @return list<array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}>
     */
    public static function tramosDeEspiritus(string $idSigno, int $anio = 2025): array
    {
        $signo = self::signoOccidentalPorId($idSigno);
        $inicio = self::fecha($anio, $signo['tropical']['desde'][0], $signo['tropical']['desde'][1]);
        return array_map(
            fn (array $t): array => [self::mesDia($t[0]), self::mesDia($t[1])],
            self::tramosDesde($inicio, $signo),
        );
    }

    /**
     * El ángel del Shem ha-Mephorash que rige una fecha (zodiaco tropical): «angel», «signo», «coro» (una de
     * CHOROS) y «tramo» (las fechas de ese ángel en el año de $fecha). Las fechas son aproximadas.
     *
     * @return array{angel: array<string, mixed>, signo: array<string, mixed>, coro: string, tramo: array<int, array<int, int>>}
     */
    public static function angelDeFecha(DateTimeInterface $fecha): array
    {
        [$numero, $signo, $tramo] = self::espirituDeFecha($fecha);
        return [
            'angel' => self::cargarEspiritus()['angeles'][$numero - 1],
            'signo' => $signo,
            'coro' => self::CHOROS[intdiv($numero - 1, 8)],
            'tramo' => $tramo,
        ];
    }

    /**
     * El demonio de la Goetia que se atribuye a una fecha: el del mismo número que su ángel. Esa correspondencia
     * es de la esoterología moderna, no de los grimorios.
     *
     * @return array{demonio: array<string, mixed>, signo: array<string, mixed>, tramo: array<int, array<int, int>>}
     */
    public static function demonioDeFecha(DateTimeInterface $fecha): array
    {
        [$numero, $signo, $tramo] = self::espirituDeFecha($fecha);
        return ['demonio' => self::cargarEspiritus()['demonios'][$numero - 1], 'signo' => $signo, 'tramo' => $tramo];
    }

    /**
     * Las cualidades de un ángel o demonio en un idioma, en una frase: «Construcción, protección, nobleza».
     * $tipo es «angel» o «demonio»; $numero, de 1 a 72.
     */
    public static function cualidadesDe(string $tipo, int $numero, string $idioma): string
    {
        if (!in_array($tipo, self::TIPOS_DE_ESPIRITU, true)) {
            throw new InvalidArgumentException("tipo debe ser uno de angel, demonio, no '$tipo'");
        }
        if ($numero < 1 || $numero > 72) {
            throw new InvalidArgumentException("numero debe estar entre 1 y 72, no $numero");
        }
        $esp = self::cargarEspiritus();
        if (!isset($esp['terminos']['separador'][$idioma])) {
            throw new InvalidArgumentException("idioma desconocido: '$idioma'");
        }
        $entrada = $esp[$tipo === 'angel' ? 'angeles' : 'demonios'][$numero - 1];
        return self::frase($entrada['cualidades'], $idioma, [$esp['cualidades']], $esp['terminos']['separador']);
    }

    /** El texto con la primera letra en mayúscula; en turco, la i pasa a İ. */
    public static function mayusculaInicial(string $texto, string $idioma): string
    {
        $primera = mb_substr($texto, 0, 1);
        $primera = $idioma === 'tr' && $primera === 'i' ? 'İ' : mb_strtoupper($primera);
        return $primera . mb_substr($texto, 1);
    }

    // --- Los árboles: el calendario celta, el horóscopo de los árboles y los de los 27 nakshatras ---
    // Tres sistemas distintos; los dos primeros no son antiguos (ver el README).

    /**
     * El mes del calendario celta de los árboles (el de Robert Graves, 1948) al que pertenece una fecha: «mes» (con su
     * letra ogham, sus fechas y sus rasgos) y «arbol» («id», «cientifico», «nombres»). El 23 de diciembre no pertenece
     * a ningún mes (es el «día del año») y devuelve null.
     *
     * @return array{mes: array<string, mixed>, arbol: array<string, mixed>}|null
     */
    public static function arbolCelta(DateTimeInterface $fecha): ?array
    {
        $clave = [(int) $fecha->format('n'), (int) $fecha->format('j')];
        $celta = self::cargarArboles()['calendario_celta'];
        if ($clave === $celta['dia_sin_arbol']) {
            return null;
        }
        foreach ($celta['meses'] as $mes) {
            if (self::enRango($clave, $mes['desde'], $mes['hasta'])) {
                return ['mes' => $mes, 'arbol' => self::arbol($mes['arbol'])];
            }
        }
        throw new LogicException(sprintf('ningún mes celta cubre el %02d/%02d', $clave[1], $clave[0]));
    }

    /**
     * El árbol del horóscopo de los árboles (el «druídico» o «galo», popular y de creación moderna) de una fecha:
     * «arbol», «tramo» (el tramo de fechas que contiene a $fecha; un árbol tiene uno, dos o tres) y «rasgos».
     *
     * @return array{arbol: array<string, mixed>, tramo: array<int, array<int, int>>, rasgos: list<string>}
     */
    public static function arbolDeFecha(DateTimeInterface $fecha): array
    {
        $clave = [(int) $fecha->format('n'), (int) $fecha->format('j')];
        foreach (self::cargarArboles()['horoscopo'] as $entrada) {
            foreach ($entrada['tramos'] as [$desde, $hasta]) {
                if (self::enRango($clave, $desde, $hasta)) {
                    return ['arbol' => self::arbol($entrada['arbol']), 'tramo' => [$desde, $hasta], 'rasgos' => $entrada['rasgos']];
                }
            }
        }
        throw new LogicException(sprintf('ningún árbol cubre el %02d/%02d', $clave[1], $clave[0]));
    }

    /**
     * La longitud eclíptica tropical de la Luna, en grados (0 a 360), en un instante. Es la serie abreviada de Meeus
     * (32 términos): comparada con una efeméride real entre 1900 y 2100, no se aleja más de 0,05°. No corrige la
     * diferencia entre el tiempo universal y el terrestre (≈ 1 minuto).
     */
    public static function longitudDeLaLuna(DateTimeInterface $instante): float
    {
        $t = (self::diaJuliano($instante) - 2451545.0) / 36525.0;
        $media = 218.3164477 + 481267.88123421 * $t - 0.0015786 * $t * $t;
        $d = deg2rad(297.8501921 + 445267.1114034 * $t - 0.0018819 * $t * $t);
        $m = deg2rad(357.5291092 + 35999.0502909 * $t - 0.0001536 * $t * $t);
        $mp = deg2rad(134.9633964 + 477198.8675055 * $t + 0.0087414 * $t * $t);
        $f = deg2rad(93.2720950 + 483202.0175233 * $t - 0.0036539 * $t * $t);
        $e = 1 - 0.002516 * $t - 0.0000074 * $t * $t;
        $suma = 0.0;
        foreach (self::TERMINOS_LUNARES as [$cd, $cm, $cmp, $cf, $c]) {
            $suma += $c * ($e ** abs($cm)) * sin($cd * $d + $cm * $m + $cmp * $mp + $cf * $f);
        }
        $a1 = deg2rad(119.75 + 131.849 * $t);
        $a2 = deg2rad(53.09 + 479264.290 * $t);
        $suma += 3958 * sin($a1) + 1962 * sin(deg2rad($media) - $f) + 318 * sin($a2);
        return self::modulo360($media + $suma / 1e6);
    }

    /**
     * El nakshatra (mansión lunar) por el que pasa la Luna en un instante, y su árbol (zodiaco sideral, Lahiri):
     * «nakshatra» y «longitud_sideral». La Luna cambia de nakshatra casi cada día, así que la fecha no basta:
     * hace falta el instante del nacimiento (con su huso horario). Un DateTime de PHP siempre es un instante.
     *
     * @return array{nakshatra: array<string, mixed>, longitud_sideral: float}
     */
    public static function nakshatraDeInstante(DateTimeInterface $instante): array
    {
        $jd = self::diaJuliano($instante);
        $sideral = self::modulo360(self::longitudDeLaLuna($instante) - (self::AYANAMSA_J2000 + ($jd - 2451545.0) / 365.25 * self::AYANAMSA_POR_ANIO));
        return ['nakshatra' => self::cargarArboles()['nakshatras'][(int) floor($sideral / (360 / 27))], 'longitud_sideral' => $sideral];
    }

    /**
     * Los rasgos de un mes celta, un árbol del horóscopo o un nakshatra en un idioma, en una frase. $sistema es «celta»
     * (clave: el id del mes, «beth»), «horoscopo» (clave: el id del árbol, «olivo») o «nakshatra» (su id, «ashlesha»).
     */
    public static function rasgosDe(string $sistema, string $clave, string $idioma): string
    {
        if (!in_array($sistema, self::SISTEMAS_DE_ARBOLES, true)) {
            throw new InvalidArgumentException("sistema debe ser uno de celta, horoscopo, nakshatra, no '$sistema'");
        }
        $arb = self::cargarArboles();
        if (!isset($arb['terminos']['separador'][$idioma])) {
            throw new InvalidArgumentException("idioma desconocido: '$idioma'");
        }
        $entradas = match ($sistema) {
            'celta' => $arb['calendario_celta']['meses'],
            'horoscopo' => $arb['horoscopo'],
            default => $arb['nakshatras'],
        };
        $campo = $sistema === 'horoscopo' ? 'arbol' : 'id';
        foreach ($entradas as $e) {
            if ($e[$campo] === $clave) {
                return self::frase($e['rasgos'], $idioma, [$arb['rasgos'], self::cargarEspiritus()['cualidades']], $arb['terminos']['separador']);
            }
        }
        throw new InvalidArgumentException("$sistema: no hay ninguno con la clave '$clave'");
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

    /** @return array<string, mixed> */
    private static function signoOccidentalPorId(string $id): array
    {
        foreach (self::occidentales() as $signo) {
            if ($signo['id'] === $id) {
                return $signo;
            }
        }
        throw new InvalidArgumentException("signo occidental desconocido: '$id'");
    }

    /** @return list<int> Los días de cada uno de los seis tramos de un signo de $dias días: los primeros se llevan el sobrante. */
    private static function largosDeTramos(int $dias): array
    {
        $sobra = $dias % 6;
        return array_merge(array_fill(0, $sobra, intdiv($dias, 6) + 1), array_fill(0, 6 - $sobra, intdiv($dias, 6)));
    }

    /** @param array<string, mixed> $signo */
    private static function finDelSigno(DateTimeImmutable $inicio, array $signo): DateTimeImmutable
    {
        [$mes, $dia] = $signo['tropical']['hasta'];
        $anio = (int) $inicio->format('Y');
        $fin = self::fecha($anio, $mes, $dia);
        return $fin >= $inicio ? $fin : self::fecha($anio + 1, $mes, $dia);
    }

    /**
     * Los seis tramos del signo que empieza en $inicio, como [desde, hasta].
     *
     * @param array<string, mixed> $signo
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>
     */
    private static function tramosDesde(DateTimeImmutable $inicio, array $signo): array
    {
        $tramos = [];
        $desde = $inicio;
        foreach (self::largosDeTramos(self::diasEntre($inicio, self::finDelSigno($inicio, $signo)) + 1) as $dias) {
            $hasta = $desde->modify('+' . ($dias - 1) . ' days');
            $tramos[] = [$desde, $hasta];
            $desde = $hasta->modify('+1 day');
        }
        return $tramos;
    }

    /** @return array{0: int, 1: int} */
    private static function mesDia(DateTimeImmutable $fecha): array
    {
        return [(int) $fecha->format('n'), (int) $fecha->format('j')];
    }

    /**
     * El número (1–72), el signo y el tramo de una fecha, en el zodiaco tropical.
     *
     * @return array{0: int, 1: array<string, mixed>, 2: array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}}
     */
    private static function espirituDeFecha(DateTimeInterface $fecha): array
    {
        $signo = self::signoOccidental($fecha, 'tropical');
        $dia = self::dia($fecha);
        $tramos = self::tramosDesde(self::inicioDelSigno($dia, $signo, 'tropical'), $signo);
        foreach ($tramos as $k => [$desde, $hasta]) {
            if ($desde <= $dia && $dia <= $hasta) {
                $posicion = (int) array_search($signo['id'], array_column(self::occidentales(), 'id'), true);
                return [$posicion * 6 + $k + 1, $signo, [self::mesDia($desde), self::mesDia($hasta)]];
            }
        }
        throw new LogicException('la fecha no cae en ninguno de los seis tramos de su signo');
    }

    /**
     * Las claves de un glosario dichas en un idioma y unidas con su separador, con la primera letra en mayúscula.
     *
     * @param list<string> $claves
     * @param list<array<string, array<string, string>>> $glosarios
     * @param array<string, string> $separador
     */
    private static function frase(array $claves, string $idioma, array $glosarios, array $separador): string
    {
        $textos = [];
        foreach ($claves as $c) {
            foreach ($glosarios as $g) {
                if (isset($g[$c])) {
                    $textos[] = $g[$c][$idioma];
                    break;
                }
            }
        }
        return self::mayusculaInicial(implode($separador[$idioma], $textos), $idioma);
    }

    /** @return array<string, mixed> */
    private static function arbol(string $id): array
    {
        return ['id' => $id] + self::cargarArboles()['arboles'][$id];
    }

    private static function diaJuliano(DateTimeInterface $instante): float
    {
        return $instante->getTimestamp() / 86400 + 2440587.5;
    }

    private static function modulo360(float $grados): float
    {
        return $grados - 360.0 * floor($grados / 360.0);
    }
}
