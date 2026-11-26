<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Cómo se busca en un libro: por párrafos (lo de siempre, igual que la versión en Java) o, en un
 * libro largo, por ventanas de oraciones, con el título del capítulo sumado a cada ventana y la
 * búsqueda en dos pasos (primero el capítulo, después el pasaje dentro de él).
 *
 * Los dos modos dan resultados distintos: el de párrafos es el que comprueban los vectores y la
 * evaluación de la muestra; el largo, el de pruebas/quijote.tsv.
 */
final class Ajustes
{
    /** Desde cuántos pasajes se considera largo un libro (el cuento de ejemplo tiene 10; el Quijote, unos 5000). */
    public const PASAJES_LIBRO_LARGO = 1000;

    public function __construct(
        /** Oraciones por ventana; 0 = cada párrafo es un pasaje. */
        public readonly int $ventana = 0,
        /** Oraciones que se repiten entre una ventana y la siguiente. */
        public readonly int $solape = 1,
        /** Un párrafo de menos de tantos caracteres se une con el siguiente; 0 = no se une. */
        public readonly int $corto = 0,
        /** Si se suman las palabras del título del capítulo a cada pasaje. */
        public readonly bool $titulo = false,
        /** Si se busca primero el capítulo y después el pasaje dentro de él. */
        public readonly bool $dosPasos = false,
    ) {
    }

    public static function parrafos(): self
    {
        return new self();
    }

    public static function largo(): self
    {
        return new self(ventana: 3, solape: 1, corto: 150, titulo: true, dosPasos: true);
    }

    public static function paraLibro(Libro $libro): self
    {
        return count($libro->pasajes) >= self::PASAJES_LIBRO_LARGO ? self::largo() : self::parrafos();
    }
}
