<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Un libro en texto plano, dividido en pasajes (párrafos) con el capítulo en que están.
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
     */
    public function __construct(
        public readonly string $titulo,
        public readonly string $autor,
        public readonly array $pasajes,
    ) {
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

        // Por referencia: el párrafo se cierra con el capítulo vigente en ese momento.
        $cerrarParrafo = static function () use (&$parrafo, &$pasajes, &$capitulo): void {
            if ($parrafo !== []) {
                $pasajes[] = ['capitulo' => $capitulo, 'texto' => implode(' ', $parrafo)];
                $parrafo = [];
            }
        };

        foreach (preg_split('/\r\n|\n|\r/', $texto) ?: [] as $linea) {
            $l = trim($linea);
            $plano = Normaliza::texto($l);

            if ($l === '') {
                $cerrarParrafo();
            } elseif (str_starts_with($plano, 'titulo:')) {
                $titulo = trim(substr($l, strpos($l, ':') + 1));
            } elseif (str_starts_with($plano, 'autor:')) {
                $autor = trim(substr($l, strpos($l, ':') + 1));
            } elseif (str_starts_with($l, '#') || str_starts_with($plano, 'capitulo ')) {
                $cerrarParrafo();
                $capitulo = trim(ltrim($l, '# '));
            } else {
                $parrafo[] = $l;
            }
        }
        $cerrarParrafo();

        return new self(
            $titulo !== '' ? $titulo : self::TITULO_POR_DEFECTO,
            $autor !== '' ? $autor : self::AUTOR_POR_DEFECTO,
            $pasajes,
        );
    }

    public static function desdeArchivo(string $ruta): self
    {
        $texto = file_get_contents($ruta);
        if ($texto === false) {
            throw new \RuntimeException("No se pudo leer el libro: $ruta");
        }
        return self::desdeTexto($texto);
    }
}
