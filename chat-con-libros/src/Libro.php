<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Un libro en texto plano (o EPUB), dividido en pasajes (párrafos) con el capítulo en que están, o
 * una colección de varios libros (por ejemplo, la obra de un autor) que se consulta como uno solo.
 *
 * Formato que se espera (ver README.md):
 *   - «Título:» y «Autor:» en cualquier línea; si faltan, se usan valores genéricos.
 *   - «# Capítulo …» (o una línea que empiece por «Capítulo ») abre un capítulo.
 *   - Los párrafos se separan con una línea en blanco.
 */
final class Libro
{
    public const TITULO_POR_DEFECTO = 'Libro sin título';
    public const AUTOR_POR_DEFECTO = 'autor desconocido';

    /**
     * @param list<array{capitulo:string, texto:string}> $pasajes
     * @param list<string> $obras los títulos de los libros que reúne, si es una colección (vacío en un libro suelto)
     */
    public function __construct(
        public readonly string $titulo,
        public readonly string $autor,
        public readonly array $pasajes,
        public readonly array $obras = [],
    ) {
    }

    /**
     * Varios libros como uno: cada pasaje queda con su capítulo precedido por el título del libro
     * («Las horas de Tomás — Capítulo 2: La carta»), de modo que toda cita dice de qué libro viene.
     *
     * @param list<Libro> $libros
     */
    public static function coleccion(string $titulo, array $libros): self
    {
        $pasajes = [];
        $obras = [];
        $autores = [];
        foreach ($libros as $libro) {
            $obras[] = $libro->titulo;
            if ($libro->autor !== self::AUTOR_POR_DEFECTO && !in_array($libro->autor, $autores, true)) {
                $autores[] = $libro->autor;
            }
            foreach ($libro->pasajes as $pasaje) {
                $pasajes[] = ['capitulo' => $libro->titulo . ' — ' . $pasaje['capitulo'], 'texto' => $pasaje['texto']];
            }
        }
        $autor = match (true) {
            $autores === [] => self::AUTOR_POR_DEFECTO,
            count($autores) === 1 => $autores[0],
            count($autores) <= 3 => implode(', ', array_slice($autores, 0, -1)) . ' y ' . $autores[count($autores) - 1],
            default => 'varios autores',
        };
        return new self($titulo, $autor, $pasajes, $obras);
    }

    public static function desdeTexto(string $texto): self
    {
        // Quita el BOM de UTF-8 si el archivo lo trae.
        if (str_starts_with($texto, "\xEF\xBB\xBF")) {
            $texto = substr($texto, 3);
        }

        $titulo = self::TITULO_POR_DEFECTO;
        $autor = self::AUTOR_POR_DEFECTO;
        $pasajes = [];
        $capitulo = 'Inicio';
        $parrafo = [];

        foreach (preg_split('/\r\n|\n|\r/', $texto) ?: [] as $linea) {
            $l = trim($linea);
            $plano = Normaliza::texto($l);

            if ($l === '') {
                self::cerrarParrafo($parrafo, $pasajes, $capitulo);
            } elseif (str_starts_with($plano, 'titulo:')) {
                $titulo = trim(substr($l, strpos($l, ':') + 1));
            } elseif (str_starts_with($plano, 'autor:')) {
                $autor = trim(substr($l, strpos($l, ':') + 1));
            } elseif (str_starts_with($l, '#') || str_starts_with($plano, 'capitulo ')) {
                self::cerrarParrafo($parrafo, $pasajes, $capitulo);
                $capitulo = trim(ltrim($l, '# '));
            } else {
                $parrafo[] = $l;
            }
        }
        self::cerrarParrafo($parrafo, $pasajes, $capitulo);

        return new self(
            $titulo !== '' ? $titulo : self::TITULO_POR_DEFECTO,
            $autor !== '' ? $autor : self::AUTOR_POR_DEFECTO,
            $pasajes,
        );
    }

    /**
     * Cierra el párrafo en curso como un pasaje del capítulo vigente, si hay algo.
     *
     * @param list<string> $parrafo
     * @param list<array{capitulo:string, texto:string}> $pasajes
     */
    private static function cerrarParrafo(array &$parrafo, array &$pasajes, string $capitulo): void
    {
        if ($parrafo !== []) {
            $pasajes[] = ['capitulo' => $capitulo, 'texto' => implode(' ', $parrafo)];
            $parrafo = [];
        }
    }

    /** «obra-de_elena» → «Obra de elena»: guiones y guiones bajos a espacios y mayúscula inicial. */
    public static function nombreLegible(string $nombre, string $porDefecto): string
    {
        $nombre = trim(str_replace(['-', '_'], ' ', $nombre));
        return $nombre === '' ? $porDefecto : mb_strtoupper(mb_substr($nombre, 0, 1)) . mb_substr($nombre, 1);
    }

    /** Un .epub se lee con Epub; todo lo demás, como texto plano. */
    public static function desdeArchivo(string $ruta): self
    {
        if (preg_match('/\.epub$/i', $ruta)) {
            return Epub::desdeArchivo($ruta);
        }
        $texto = file_get_contents($ruta);
        if ($texto === false) {
            throw new \RuntimeException("No se pudo leer el libro: $ruta");
        }
        return self::desdeTexto($texto);
    }
}
