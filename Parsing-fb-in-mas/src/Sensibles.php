<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Busca en un texto términos de las categorías que la ley chilena de datos (21.719) trata como
 * sensibles: salud, convicciones religiosas, afiliación política o sindical, vida sexual y origen
 * étnico. `defamatory-content-review/` no las cubre (ver su `docs/RIESGOS.md`), por eso esta lista.
 *
 * Es una lista CORTA y de palabras sueltas: que no marque nada no quiere decir que no haya un dato
 * sensible, y marcar no quiere decir que lo haya («Iglesia» en el nombre de un barrio). Solo sirve
 * para que una persona mire el texto con más cuidado. No reemplaza la revisión humana.
 */
final class Sensibles
{
    /** categoría => palabras (minúsculas y sin tildes; se comparan como palabra completa o raíz con «*») */
    private const TERMINOS = [
        'salud' => ['cancer', 'tumor', 'vih', 'sida', 'diabetes', 'depresion', 'ansiedad', 'bipolar', 'esquizofrenia',
            'autismo', 'epilepsia', 'discapacidad', 'diagnostic*', 'enfermedad', 'quimioterapia',
            'illness', 'disease', 'disorder', 'diagnosed', 'disability', 'therapy'],
        'religión' => ['catolic*', 'evangelic*', 'musulman*', 'judio*', 'judia*', 'budista', 'ateo', 'atea', 'testigo de jehova',
            'mormon*', 'islam', 'christian', 'muslim', 'jewish', 'buddhist', 'atheist', 'pastor', 'creyente'],
        'política o sindical' => ['militante', 'sindicato', 'sindical', 'partido comunista', 'partido socialista',
            'derechista', 'izquierdista', 'comunista', 'socialista', 'union member',
            'political party', 'communist'],
        'vida sexual' => ['gay', 'lesbiana', 'bisexual', 'transgenero', 'homosexual', 'orientacion sexual', 'transgender',
            'sexual orientation', 'lgbt*'],
        'origen étnico' => ['mapuche', 'indigena', 'afrodescendiente', 'gitano', 'etnia', 'ethnicity', 'indigenous'],
    ];

    /**
     * @return list<string> las categorías que aparecen, en el orden de la lista y sin repetir
     */
    public static function buscar(string $texto): array
    {
        $normal = ' ' . self::normalizar($texto) . ' ';
        $categorias = [];
        foreach (self::TERMINOS as $categoria => $terminos) {
            foreach ($terminos as $termino) {
                $raiz = str_ends_with($termino, '*');
                $t = $raiz ? substr($termino, 0, -1) : $termino;
                $patron = '/(?<![a-z0-9])' . preg_quote($t, '/') . ($raiz ? '' : '(?![a-z0-9])') . '/';
                if (preg_match($patron, $normal) === 1) {
                    $categorias[] = $categoria;
                    break;
                }
            }
        }
        return $categorias;
    }

    /** Minúsculas, sin tildes ni signos: lo único que compara `buscar()`. */
    private static function normalizar(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $t));
    }
}
