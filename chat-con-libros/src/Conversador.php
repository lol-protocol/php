<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Responde preguntas sobre un libro citando sus pasajes.
 *
 * No genera texto nuevo: o saluda, o da el autor, el título o un resumen armado con las
 * palabras más repetidas (intencion), o busca en el libro los pasajes más parecidos a la
 * pregunta y los cita con su capítulo (pasajesPara). Si nada pasa el umbral, lo dice.
 */
final class Conversador
{
    /** Puntaje coseno mínimo para citar un pasaje. Con menos, el libro «no habla de eso». */
    public const UMBRAL = 0.10;

    /** Cuántos pasajes se citan como mucho en una respuesta. */
    public const MAX_CITAS = 2;

    /** En la búsqueda en dos pasos: cuántos pasajes de los mejores votan por su capítulo. */
    public const PASAJES_PARA_CAPITULO = 5;

    /** Índice de los pasajes; en un libro largo, con el título del capítulo sumado a cada uno. */
    private Indice $indice;

    /** Solo en la búsqueda en dos pasos: índice del texto de los pasajes, sin el título. */
    private ?Indice $indiceTexto = null;

    /** @var list<array{capitulo:string, texto:string}> lo que se busca: párrafos o ventanas de oraciones */
    private array $unidades;

    private Ajustes $ajustes;

    /** Solo en un libro largo: dice con otras palabras el mejor pasaje en vez de citarlo tal cual. */
    private ?Parafrasea $parafrasea;

    /** Parafrasea::MODERNO u ORIGINAL; cambia con «/original» y «/moderno» o con estilo(). */
    private string $estilo = Parafrasea::MODERNO;

    /** @var list<string> las palabras más repetidas del libro */
    private array $temas;

    public function __construct(
        private readonly Libro $libro,
        private readonly ?Sinonimos $sinonimos = null,
        ?Ajustes $ajustes = null,
        ?Parafrasea $parafrasea = null,
    ) {
        $this->ajustes = $ajustes ?? Ajustes::paraLibro($libro);
        $this->unidades = $this->ajustes->ventana > 0
            ? Ventanas::partir($libro->pasajes, $this->ajustes->ventana, $this->ajustes->solape, $this->ajustes->corto)
            : $libro->pasajes;

        $cuenta = [];
        foreach ($libro->pasajes as $pasaje) {
            // Para mostrar los temas se cuenta la palabra tal como está escrita, no su raíz.
            foreach (Normaliza::palabras($pasaje['texto']) as $palabra) {
                $cuenta[$palabra] = ($cuenta[$palabra] ?? 0) + 1;
            }
        }

        $terminosTexto = $terminosIndice = [];
        foreach ($this->unidades as $unidad) {
            $t = Normaliza::terminos($unidad['texto']);
            $terminosTexto[] = $t;
            $terminosIndice[] = $this->ajustes->titulo
                ? array_merge($t, Normaliza::terminos((string) preg_replace('/^[^:]*:\s*/', '', $unidad['capitulo'])))
                : $t;
        }
        $this->indice = new Indice($terminosIndice);
        if ($this->ajustes->dosPasos) {
            $this->indiceTexto = new Indice($terminosTexto);
        }
        $this->parafrasea = $parafrasea ?? ($this->ajustes->dosPasos
            ? new Parafrasea(dirname(__DIR__) . '/datos/modernizar.json', self::pesosIdf($terminosTexto))
            : null);

        $pares = [];
        foreach ($cuenta as $t => $c) {
            $pares[] = ['termino' => (string) $t, 'veces' => $c];
        }
        usort($pares, static fn (array $a, array $b): int => ($b['veces'] <=> $a['veces'])
            ?: strcmp($a['termino'], $b['termino']));
        $this->temas = array_map(
            static fn (array $p): string => $p['termino'],
            array_slice($pares, 0, 5),
        );
    }

    public function libro(): Libro
    {
        return $this->libro;
    }

    /** Si este libro se responde con paráfrasis (los libros largos), y no solo citándolo. */
    public function parafrasea(): bool
    {
        return $this->parafrasea !== null;
    }

    public function estilo(): string
    {
        return $this->estilo;
    }

    public function conEstilo(string $estilo): void
    {
        if ($estilo !== Parafrasea::MODERNO && $estilo !== Parafrasea::ORIGINAL) {
            throw new \InvalidArgumentException("Estilo desconocido: $estilo");
        }
        $this->estilo = $estilo;
    }

    /**
     * El idf de cada término sobre los pasajes, para que al elegir la oración las palabras raras
     * («Viedma») pesen más que las comunes («capitán»). Mismo cálculo que Indice.
     *
     * @param list<list<string>> $terminosPorPasaje
     * @return array<string,float>
     */
    private static function pesosIdf(array $terminosPorPasaje): array
    {
        $df = [];
        foreach ($terminosPorPasaje as $terminos) {
            foreach (array_unique($terminos) as $t) {
                $df[$t] = ($df[$t] ?? 0) + 1;
            }
        }
        $n = count($terminosPorPasaje);
        $pesos = [];
        foreach ($df as $t => $d) {
            $pesos[(string) $t] = log(($n + 1) / ($d + 1)) + 1.0;
        }
        return $pesos;
    }

    public function responder(string $pregunta): string
    {
        return $this->intencion($pregunta) ?? $this->citar($pregunta);
    }

    /**
     * La respuesta si la pregunta es de las que se contestan con reglas (vacía, saludo, ayuda,
     * autor, título o resumen); null si hay que buscar en el contenido del libro.
     */
    public function intencion(string $pregunta): ?string
    {
        // Sin los signos de apertura («¿», «¡») para que los patrones de abajo anclados al inicio sigan valiendo.
        $p = (string) preg_replace('/^[¿¡\s]+/u', '', Normaliza::texto(trim($pregunta)));
        $titulo = $this->libro->titulo;
        $autor = $this->libro->autor;

        if ($p === '') {
            return 'Escribe una pregunta sobre el libro.';
        }
        if (preg_match('/^(hola|buenas|buenos dias|buenas tardes|buenas noches|saludos|hey)\b/', $p)) {
            return "Hola. Soy el asistente de «{$titulo}», de {$autor}. Pregúntame algo del libro: "
                . 'te respondo citando sus pasajes.';
        }
        if (preg_match('/\b(ayuda|que puedo preguntar)\b/', $p)) {
            return 'Puedo responder citando pasajes del libro. Pregunta por un tema, un personaje '
                . 'o un objeto (por ejemplo, «¿qué pasa con el reloj?»), o pide «un resumen», '
                . '«quién lo escribió» o «cómo se llama». Si el libro no habla de algo, te lo digo.';
        }
        if (preg_match('/^(quien|quienes)\b.*\b(escribio|escrito|autor|autora)\b/', $p)) {
            return "«{$titulo}» lo escribió {$autor}.";
        }
        if (preg_match('/\b(como se llama|titulo del libro|nombre del libro)\b/', $p)) {
            return $this->libro->obras === []
                ? "El libro se llama «{$titulo}»."
                : "Esta colección se llama «{$titulo}» y reúne: " . self::lista($this->libro->obras) . '.';
        }
        if (preg_match('/^\/(original|moderno)$/', $p, $m)) {
            if ($this->parafrasea === null) {
                return 'Este libro se cita tal cual: el estilo «moderno» y el «original» son para los libros largos.';
            }
            $this->estilo = $m[1];
            return $m[1] === Parafrasea::ORIGINAL
                ? 'Estilo original: te muestro el texto como lo escribió el autor. Escribe /moderno para volver al español de hoy.'
                : 'Estilo moderno: te lo cuento en español de hoy, con el texto original debajo. Escribe /original para verlo solo como lo escribió el autor.';
        }
        if (preg_match('/^(de que (trata|va|se trata)|resumen|resumeme)\b/', $p)
            || preg_match('/\bde que (trata|va) el libro\b/', $p)) {
            return $this->resumen();
        }
        return null;
    }

    /**
     * Los pasajes del libro que más se parecen a la pregunta (con sus sinónimos), del más
     * parecido al menos, y solo los que pasan el umbral.
     *
     * @return list<array{capitulo:string, texto:string, puntaje:float}>
     */
    public function pasajesPara(string $pregunta, int $max = self::MAX_CITAS): array
    {
        $terminos = $this->terminosDe($pregunta);
        if ($this->indiceTexto !== null) {
            return $this->enDosPasos($terminos, $max);
        }
        $pasajes = [];
        foreach ($this->indice->buscar($terminos, $max) as $r) {
            if ($r['puntaje'] < self::UMBRAL) {
                continue;
            }
            $pasaje = $this->unidades[$r['pasaje']];
            $pasajes[] = [
                'capitulo' => $pasaje['capitulo'],
                'texto' => $pasaje['texto'],
                'puntaje' => $r['puntaje'],
            ];
        }
        return $pasajes;
    }

    /**
     * Búsqueda en dos pasos para un libro largo: (1) los capítulos con más puntaje entre los 5
     * mejores pasajes (con el título sumado); (2) en cada uno de los $max primeros, el pasaje que
     * más se parece a la pregunta por su texto solo. Los títulos del Quijote resumen cada capítulo,
     * así que sirven para elegirlo, pero no para elegir una frase dentro de él.
     *
     * @param list<string> $terminos
     * @return list<array{capitulo:string, texto:string, puntaje:float}>
     */
    private function enDosPasos(array $terminos, int $max): array
    {
        $mejores = $this->indice->buscar($terminos, self::PASAJES_PARA_CAPITULO);
        if ($mejores === [] || $mejores[0]['puntaje'] < self::UMBRAL) {
            return [];
        }
        $porCapitulo = [];
        foreach ($mejores as $r) {
            $capitulo = $this->unidades[$r['pasaje']]['capitulo'];
            $porCapitulo[$capitulo] = ($porCapitulo[$capitulo] ?? 0.0) + $r['puntaje'];
        }
        arsort($porCapitulo);

        $porTexto = $this->indiceTexto?->buscar($terminos, count($this->unidades)) ?? [];
        $pasajes = [];
        foreach (array_slice($porCapitulo, 0, max(0, $max), true) as $capitulo => $puntaje) {
            $elegido = null;
            foreach ($porTexto as $r) {
                if ($r['puntaje'] > 0.0 && $this->unidades[$r['pasaje']]['capitulo'] === (string) $capitulo) {
                    $elegido = $r['pasaje'];
                    break;
                }
            }
            if ($elegido === null) {
                // Ninguna palabra de la pregunta está en el texto del capítulo, solo en su título.
                foreach ($mejores as $r) {
                    if ($this->unidades[$r['pasaje']]['capitulo'] === (string) $capitulo) {
                        $elegido = $r['pasaje'];
                        break;
                    }
                }
            }
            if ($elegido !== null) {
                $pasajes[] = [
                    'capitulo' => (string) $capitulo,
                    'texto' => $this->unidades[$elegido]['texto'],
                    'puntaje' => $puntaje,
                ];
            }
        }
        return $pasajes;
    }

    /**
     * Los términos de búsqueda de una pregunta, con sus sinónimos.
     *
     * @return list<string>
     */
    private function terminosDe(string $pregunta): array
    {
        $terminos = Normaliza::terminos($pregunta);
        return $this->sinonimos !== null ? $this->sinonimos->expandir($terminos) : $terminos;
    }

    /** El mensaje de «no encuentro eso», que también usan el modo con IA y la web. */
    public function noEncuentro(): string
    {
        return "No encuentro eso en «{$this->libro->titulo}». Prueba con otras palabras "
            . 'o pregunta por algún tema del libro.';
    }

    private function resumen(): string
    {
        $temas = $this->temas === [] ? 'nada que destacar' : implode(', ', $this->temas);
        if ($this->libro->obras !== []) {
            return "«{$this->libro->titulo}», de {$this->libro->autor}, reúne " . count($this->libro->obras) . ' libros: '
                . self::lista($this->libro->obras) . ". Lo que más se repite: $temas.";
        }
        $primero = $this->libro->pasajes[0]['texto'] ?? '';
        $inicio = $primero === '' ? '' : ' Empieza así: «' . self::extracto($primero, 300) . '»';
        return "«{$this->libro->titulo}», de {$this->libro->autor}, trata sobre: $temas.$inicio";
    }

    private function citar(string $pregunta): string
    {
        $pasajes = $this->pasajesPara($pregunta);
        if ($pasajes === []) {
            return $this->noEncuentro();
        }
        if ($this->parafrasea !== null) {
            $r = $this->parafrasea->responder($this->terminosDe($pregunta), $pregunta, $pasajes[0]['capitulo'], $pasajes[0]['texto'], $this->estilo);
            $lineas = [$r['texto']];
            if (isset($pasajes[1])) {
                $lineas[] = 'También: [' . $pasajes[1]['capitulo'] . '] «' . self::extracto($pasajes[1]['texto']) . '»';
            }
            return implode("\n\n", $lineas);
        }

        $lineas = ['Esto dice el libro:'];
        foreach ($pasajes as $pasaje) {
            $lineas[] = '— [' . $pasaje['capitulo'] . '] «' . self::extracto($pasaje['texto']) . '»';
        }
        return implode("\n\n", $lineas);
    }

    /** «A», «A y B», «A, B y C», con cada título entre comillas angulares. @param list<string> $titulos */
    private static function lista(array $titulos): string
    {
        $citados = array_map(static fn (string $t): string => "«{$t}»", $titulos);
        return count($citados) <= 1
            ? implode('', $citados)
            : implode(', ', array_slice($citados, 0, -1)) . ' y ' . $citados[count($citados) - 1];
    }

    /** Corta un texto en el último espacio antes de $max caracteres y agrega «…». */
    public static function extracto(string $texto, int $max = 350): string
    {
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }
        $corte = mb_substr($texto, 0, $max);
        $espacio = mb_strrpos($corte, ' ');
        if ($espacio !== false && $espacio > $max * 0.6) {
            $corte = mb_substr($corte, 0, $espacio);
        }
        return rtrim($corte, ' ,;:') . '…';
    }
}
