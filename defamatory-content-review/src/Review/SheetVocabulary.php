<?php

namespace DefamatoryContentReview\Review;

/**
 * Los nombres que una planilla puede traer en vez de una palabra, y que acaban
 * escritos en un archivo PHP de config/: la lista de una excepción y la etiqueta
 * de una forma. Sólo valen los que genera bin/review-sheets.php; cualquier otro
 * valor es un problema de la fila, no un cambio. Colaborador de ReviewSheet.
 */
final class SheetVocabulary
{
    /** Las listas de una línea que bin/review-sheets.php pone en la columna `categoria` de las filas `excepción`. */
    public const LISTS = ['everyday', 'legit'];
    /** Los rasgos de una forma generada (ver FormSeverity): `pl`, `f`, `m.sg`, `f.pl`. */
    public const TAG = '/^[a-z]+(?:\.[a-z]+)?$/';

    /**
     * Por qué la fila trae un nombre que el generador no escribe, o null si está bien. Sólo mira las
     * filas cuya respuesta acaba escribiendo ese nombre.
     *
     * @param array<string,string> $row
     */
    public static function problem(array $row, string $answer): ?string
    {
        if ($row['seccion'] === 'excepción' && $answer === 'no' && !in_array($row['categoria'], self::LISTS, true)) {
            return "la lista «{$row['categoria']}» no es una de las que se pueden tocar (" . implode(', ', self::LISTS) . ')';
        }
        if ($row['seccion'] === 'forma' && preg_match('/forma:(\S+) de:/u', $row['marcas'], $form) === 1 && preg_match(self::TAG, $form[1]) !== 1) {
            return "«{$form[1]}» no es una etiqueta de forma (m.sg, f.pl, pl, f…)";
        }

        return null;
    }
}
