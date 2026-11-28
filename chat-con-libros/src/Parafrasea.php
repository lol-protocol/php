<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Dice con otras palabras un pasaje del libro, solo con reglas y tablas: no inventa nada.
 *
 * Pasos: (1) elige la oración del pasaje que más tiene que ver con la pregunta, y recorta las muy
 * largas a su cláusula principal; (2) en el estilo «moderno», actualiza la ortografía y las
 * palabras antiguas (datos/modernizar.json), pasa «díjole» a «le dijo» y convierte el diálogo con
 * raya en «Sancho respondió: «…»»; (3) lo presenta con una frase según el tipo de pregunta.
 *
 * Garantía (comprobar): cada palabra de la respuesta sale del pasaje, de las tablas o de la frase
 * de presentación. Si alguna no, se descarta la paráfrasis y se devuelve la cita tal cual.
 * Solo PHP: el port a Java no la tiene.
 */
final class Parafrasea
{
    /** Ortografía y palabras de hoy, con el texto original debajo. */
    public const MODERNO = 'moderno';

    /** Solo elige la oración y la presenta; el texto queda como lo escribió el autor. */
    public const ORIGINAL = 'original';

    /** Largo (en caracteres) desde el que una oración se recorta a su cláusula principal. */
    private const ORACION_LARGA = 320;

    /** Una oración más corta que esto no se entiende sola: se le suma la siguiente. */
    private const ORACION_CORTA = 90;

    private const VERBOS_DECIR = '(?:dijo|respondió|replicó|preguntó|añadió|contestó|prosiguió|decía|respondía|replicaba|preguntaba|volvió a decir)(?: (?:a esto|entonces|luego|a esta sazón))?';

    /** Pretéritos irregulares que solo llevan acento escrito por el pronombre pegado («díjole»). */
    private const IRREGULARES = ['dijo', 'hizo', 'puso', 'tuvo', 'trajo', 'quiso', 'vino', 'pudo', 'supo', 'anduvo', 'estuvo', 'avino', 'sucedió',
        'dijeron', 'hicieron', 'pusieron', 'tuvieron', 'trajeron', 'quisieron', 'vinieron', 'pudieron', 'supieron'];

    /** Imperativos de verbos en -iar que se siguen escribiendo con pronombre pegado («envíanos», «desvíate»). */
    private const IMPERATIVOS_EN_IAR = ['envía', 'desvía', 'confía', 'guía', 'fía', 'cría', 'espía', 'varía', 'lía'];

    /** Formas que terminan como un verbo con pronombre pegado pero no lo son (un nombre propio). */
    private const NO_ENCLITICOS = ['euríalo'];

    /** @var array<string,string> */
    private array $frases;

    /** @var array<string,string> */
    private array $palabras;

    /** @var array<string,true> palabras que pueden salir sin estar en el pasaje: las de las tablas y las de «le dijo» */
    private array $permitidas = [];

    /**
     * @param string $rutaTablas datos/modernizar.json
     * @param array<string,float> $pesos peso de cada término de búsqueda (su idf en el libro); uno sin peso vale 1
     * @param float $castigoPregunta por cuánto se multiplica el puntaje de una oración que es una pregunta
     */
    public function __construct(
        string $rutaTablas,
        private readonly array $pesos = [],
        private readonly float $castigoPregunta = 0.5,
    ) {
        $t = json_decode((string) file_get_contents($rutaTablas), true, 512, JSON_THROW_ON_ERROR);
        $this->frases = $t['frases'];
        $this->palabras = $t['palabras'] + ($t['infinitivos'] ?? []) + $t['sinonimos'];
        foreach ([$this->frases, $this->palabras] as $tabla) {
            foreach ($tabla as $nueva) {
                $this->permitir($nueva);
            }
        }
    }

    /**
     * @param list<string> $terminos la pregunta ya normalizada (y con sus sinónimos), para elegir la oración
     * @return array{texto:string, parafraseado:bool}
     */
    public function responder(array $terminos, string $pregunta, string $capitulo, string $pasaje, string $estilo = self::MODERNO): array
    {
        $elegido = $this->elegir($pasaje, $terminos);
        $intro = $this->presentacion($pregunta, $capitulo);

        if ($estilo === self::ORIGINAL) {
            return ['texto' => "$intro «{$elegido}»", 'parafraseado' => false];
        }
        $nuevo = $this->modernizar($this->dialogo($elegido));
        if (!$this->comprobar("$intro $nuevo", "$pasaje $intro")) {
            return ['texto' => "$intro «{$elegido}»", 'parafraseado' => false];
        }
        return [
            'texto' => "$intro $nuevo" . ($nuevo === $elegido ? '' : "\n   Texto original: «{$elegido}»"),
            'parafraseado' => $nuevo !== $elegido,
        ];
    }

    // ---- (1) elegir -----------------------------------------------------------------------

    /**
     * La oración del pasaje con más palabras de la pregunta (las raras pesan más; una oración que
     * es una pregunta, la mitad). Si es corta, o es una pregunta, se le suma la siguiente: sola no
     * se entiende, o no trae la respuesta.
     *
     * @param list<string> $terminos
     */
    public function elegir(string $pasaje, array $terminos): string
    {
        $oraciones = Ventanas::oraciones($pasaje);
        if ($oraciones === []) {
            return trim($pasaje);
        }
        $mejor = 0;
        $mejorPuntaje = -1.0;
        foreach ($oraciones as $i => $o) {
            $p = $this->coincidencias($o, $terminos) * (str_contains($o, '?') ? $this->castigoPregunta : 1.0);
            if ($p > $mejorPuntaje) {
                [$mejor, $mejorPuntaje] = [$i, $p];
            }
        }
        $texto = $this->recortar($oraciones[$mejor], $terminos);
        if ((mb_strlen($texto) < self::ORACION_CORTA || str_contains($oraciones[$mejor], '?')) && isset($oraciones[$mejor + 1])) {
            $texto .= ' ' . $this->recortar($oraciones[$mejor + 1], $terminos);
        }
        return $texto;
    }

    /** @param list<string> $terminos */
    private function coincidencias(string $texto, array $terminos): float
    {
        $p = 0.0;
        foreach (array_intersect(array_unique(Normaliza::terminos($texto)), $terminos) as $t) {
            $p += $this->pesos[$t] ?? 1.0;
        }
        return $p;
    }

    /**
     * Una oración larga se queda con su primera cláusula (separada por «;» o «:») y con la que más
     * coincide con la pregunta, marcando los cortes con «(…)».
     *
     * @param list<string> $terminos
     */
    private function recortar(string $oracion, array $terminos): string
    {
        if (mb_strlen($oracion) <= self::ORACION_LARGA) {
            return $oracion;
        }
        $clausulas = preg_split('/(?<=[;:])\s+/u', $oracion) ?: [$oracion];
        if (count($clausulas) < 2) {
            return $oracion;
        }
        $mejor = 0;
        $mejorPuntaje = -1.0;
        foreach ($clausulas as $i => $c) {
            $p = $this->coincidencias($c, $terminos);
            if ($p > $mejorPuntaje) {
                [$mejor, $mejorPuntaje] = [$i, $p];
            }
        }
        $partes = $mejor === 0 ? [$clausulas[0]] : [$clausulas[0], '(…)', $clausulas[$mejor]];
        $texto = rtrim(implode(' ', $partes), ';:, ');
        return $mejor < count($clausulas) - 1 ? "$texto (…)" : $texto;
    }

    // ---- (2) modernizar -------------------------------------------------------------------

    /**
     * «— Yo no lo sé —respondió Sancho— que lo veremos.» → «Sancho respondió: «Yo no lo sé, que lo
     * veremos.»». Si la raya no cierra con quien habla, lo que sigue es narración y queda fuera de las comillas.
     */
    public function dialogo(string $texto): string
    {
        $raya = '[-—–]';
        $quien = '(?:(?:don|doña|el|la) )?(?:[\p{Lu}][\p{L}]+|(?<=el |la )[\p{L}]{4,})'
            . '(?: (?:de la|del|de|Panza|Quijote|Carrasco)(?!\p{L})(?: [\p{Lu}][\p{L}]+)?)?(?!\p{L})';
        $re = '/^' . $raya . '\s*(?<habla>.+?)\s*' . $raya . '\s*(?<verbo>' . self::VERBOS_DECIR . ')\s+'
            . '(?<quien>' . $quien . ')\s*(?<cierre>' . $raya . ')?\s*(?<sep>[.,;:]?)\s*(?<resto>.*)$/u';
        if (!preg_match($re, trim($texto), $m)) {
            return $texto;
        }
        $habla = (string) preg_replace('/^[«\s]+|[»,\s]+$/u', '', $m['habla']);
        $resto = trim($m['resto']);
        // Con raya de cierre o con coma, lo que sigue es la misma persona hablando.
        if ($resto !== '' && ($m['cierre'] !== '' || $m['sep'] === ',')) {
            $habla .= ($m['sep'] === '.' ? '. ' : ', ') . $resto;
            $resto = '';
        }
        $salida = self::mayuscula($m['quien']) . " {$m['verbo']}: «{$habla}»";
        return $resto === '' ? $salida : "$salida $resto";
    }

    /** Las tablas de datos/modernizar.json y los pronombres pegados al verbo, palabra por palabra. */
    public function modernizar(string $texto): string
    {
        foreach ($this->frases as $vieja => $nueva) {
            $texto = self::reemplazarPalabra($texto, $vieja, $nueva);
        }
        return preg_replace_callback('/\b\p{L}+\b/u', function (array $m): string {
            $min = mb_strtolower($m[0]);
            $nueva = $this->palabras[$min] ?? $this->enclitico($min);
            return $nueva === null ? $m[0] : self::comoMayuscula($m[0], $nueva);
        }, $texto) ?? $texto;
    }

    /**
     * «díjole» → «le dijo», «llegóse» → «se llegó», «seguíale» → «le seguía». Solo verbos en
     * pretérito o imperfecto: son los que llevan pronombre pegado en el Quijote y ya no en el
     * español de hoy. Los gerundios e infinitivos con pronombre («diciéndole») se dejan igual.
     */
    public function enclitico(string $w): ?string
    {
        if (!preg_match('/^(?<verbo>\p{L}{3,}?)(?<pron>les|los|las|nos|le|la|lo|se|me|te)$/u', $w, $m)
            || in_array($w, self::NO_ENCLITICOS, true)
            || in_array($m['verbo'], self::IMPERATIVOS_EN_IAR, true)) {
            return null;
        }
        $v = $m['verbo'];
        $sin = strtr($v, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        if (preg_match('/[^i]ó$|ió$/u', $v) || in_array($v, ['dio', 'vio'], true)) {
            $verbo = $v;                                        // preguntóle, volvióse, diole
        } elseif (in_array($sin, self::IRREGULARES, true) && $sin !== $v) {
            $verbo = $sin;                                      // díjole → dijo, pusiéronle → pusieron
        } elseif (preg_match('/ába$|ában$/u', $v)) {
            $verbo = $sin;                                      // mirábale → miraba
        } elseif (preg_match('/(?:[^aeiou]|(?<=g)u)ían?$/u', $v) && mb_strlen($v) > 4) {
            $verbo = $v;                                        // seguíale → seguía
        } else {
            return null;
        }
        $this->permitir("{$m['pron']} $verbo");
        return "{$m['pron']} $verbo";
    }

    // ---- (3) presentar --------------------------------------------------------------------

    /** «Lo cuenta la primera parte, capítulo VIII:», según de qué es la pregunta. */
    public function presentacion(string $pregunta, string $capitulo): string
    {
        $donde = self::lugar($capitulo);
        $p = Normaliza::texto($pregunta);
        $intro = match (true) {
            (bool) preg_match('/\bpor que\b/', $p) => "La razón está en $donde:",
            (bool) preg_match('/\bquien(es)?\b/', $p) => "De eso habla $donde:",
            (bool) preg_match('/\b(que (le )?(paso|sucedio|hizo|hacia)|como (fue|murio|gobern\w*|encanto))\b/', $p) => "Lo cuenta $donde:",
            (bool) preg_match('/\bdonde\b/', $p) => "El lugar aparece en $donde:",
            default => "Según $donde:",
        };
        $this->permitir($intro);
        return $intro;
    }

    /** «Parte I, cap. VIII: Del buen suceso…» → «la primera parte, capítulo VIII». */
    private static function lugar(string $capitulo): string
    {
        if (preg_match('/^Parte (I+), cap\. ([IVXLC]+)/', $capitulo, $m)) {
            return ($m[1] === 'I' ? 'la primera parte' : 'la segunda parte') . ", capítulo {$m[2]}";
        }
        return $capitulo;
    }

    // ---- garantía -------------------------------------------------------------------------

    /** Cada palabra del resultado tiene que estar en el origen o venir de una tabla o de la presentación. */
    public function comprobar(string $resultado, string $origen): bool
    {
        if (!mb_check_encoding($resultado, 'UTF-8')) {
            return false;
        }
        $base = array_fill_keys(self::palabrasDe($origen), true) + $this->permitidas;
        foreach (self::palabrasDe($resultado) as $w) {
            if (!isset($base[$w])) {
                return false;
            }
        }
        return true;
    }

    /** @return list<string> */
    private static function palabrasDe(string $s): array
    {
        preg_match_all('/\p{L}+/u', mb_strtolower($s), $m);
        return $m[0];
    }

    private function permitir(string $texto): void
    {
        foreach (self::palabrasDe($texto) as $w) {
            $this->permitidas[$w] = true;
        }
    }

    private static function reemplazarPalabra(string $texto, string $vieja, string $nueva): string
    {
        return preg_replace_callback(
            '/(?<!\p{L})' . preg_quote($vieja, '/') . '(?!\p{L})/iu',
            static fn (array $m): string => self::comoMayuscula($m[0], $nueva),
            $texto,
        ) ?? $texto;
    }

    private static function comoMayuscula(string $original, string $nueva): string
    {
        $primera = mb_substr($original, 0, 1);
        return $primera !== mb_strtolower($primera) ? self::mayuscula($nueva) : $nueva;
    }

    private static function mayuscula(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }
}
