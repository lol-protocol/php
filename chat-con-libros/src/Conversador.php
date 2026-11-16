<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Responde preguntas sobre un libro citando sus pasajes.
 *
 * No genera texto nuevo: o saluda, o da el autor, el título o un resumen armado con las
 * palabras más repetidas, o busca en el libro los pasajes más parecidos a la pregunta y
 * los cita con su capítulo. Si nada pasa el umbral, lo dice.
 */
final class Conversador
{
    /** Puntaje coseno mínimo para citar un pasaje. Con menos, el libro «no habla de eso». */
    public const UMBRAL = 0.10;

    /** Cuántos pasajes se citan como mucho en una respuesta. */
    public const MAX_CITAS = 2;

    private Indice $indice;

    /** @var list<string> las palabras más repetidas del libro */
    private array $temas;

    public function __construct(private readonly Libro $libro)
    {
        $terminosPorPasaje = [];
        $cuenta = [];
        foreach ($libro->pasajes as $pasaje) {
            $terminosPorPasaje[] = Normaliza::terminos($pasaje['texto']);
            // Para mostrar los temas se cuenta la palabra tal como está escrita, no su raíz.
            foreach (Normaliza::palabras($pasaje['texto']) as $palabra) {
                $cuenta[$palabra] = ($cuenta[$palabra] ?? 0) + 1;
            }
        }
        $this->indice = new Indice($terminosPorPasaje);

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

    public function responder(string $pregunta): string
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
            return "El libro se llama «{$titulo}».";
        }
        if (preg_match('/^(de que (trata|va|se trata)|resumen|resumeme)\b/', $p)
            || preg_match('/\bde que (trata|va) el libro\b/', $p)) {
            return $this->resumen();
        }

        return $this->citar($pregunta);
    }

    private function resumen(): string
    {
        $primero = $this->libro->pasajes[0]['texto'] ?? '';
        $temas = $this->temas === [] ? 'nada que destacar' : implode(', ', $this->temas);
        $inicio = $primero === '' ? '' : ' Empieza así: «' . self::extracto($primero, 300) . '»';
        return "«{$this->libro->titulo}», de {$this->libro->autor}, trata sobre: $temas.$inicio";
    }

    private function citar(string $pregunta): string
    {
        $mejores = array_filter(
            $this->indice->buscar(Normaliza::terminos($pregunta), self::MAX_CITAS),
            static fn (array $r): bool => $r['puntaje'] >= self::UMBRAL,
        );
        if ($mejores === []) {
            return "No encuentro eso en «{$this->libro->titulo}». Prueba con otras palabras "
                . 'o pregunta por algún tema del libro.';
        }

        $lineas = ['Esto dice el libro:'];
        foreach ($mejores as $r) {
            $pasaje = $this->libro->pasajes[$r['pasaje']];
            $lineas[] = '— [' . $pasaje['capitulo'] . '] «' . self::extracto($pasaje['texto']) . '»';
        }
        return implode("\n\n", $lineas);
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
