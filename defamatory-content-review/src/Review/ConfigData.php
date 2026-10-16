<?php

namespace DefamatoryContentReview\Review;

/**
 * Los archivos de config/ son sólo datos: un `return [...]` de textos, números y
 * `true`/`false`/`null`. Lo que una planilla escribe en ellos tiene que seguir
 * siéndolo, y se comprueba con el tokenizador, sin ejecutar nada: una planilla
 * viene de fuera, y lo que ella escribió no debe correr jamás, ni siquiera en la
 * vista previa de `bin/apply-review.php`.
 *
 * Es una lista de lo permitido, no de lo prohibido: una llamada, una variable,
 * un `new`, un `include`, un texto con interpolación o un `?>` no están en ella.
 * Si un config necesita algo más, se añade aquí a propósito, y
 * ConfigDataTest::testEveryRealConfigFileIsData lo comprueba contra todos los reales.
 */
final class ConfigData
{
    private const TOKENS = [
        T_OPEN_TAG, T_RETURN, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT,
        T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_DOUBLE_ARROW,
    ];
    /** `.` une dos textos y `-` es el signo de un número: con sólo literales como operandos no ejecutan nada. */
    private const SYMBOLS = [',', '[', ']', ';', '.', '-'];
    private const WORDS = ['true', 'false', 'null'];

    /**
     * Qué es lo primero del texto que no es un dato, o null si todo lo es.
     *
     * @throws \ParseError si el texto ni siquiera es PHP válido
     */
    public static function foreign(string $text): ?string
    {
        foreach (token_get_all($text, TOKEN_PARSE) as $token) {
            if (is_string($token)) {
                if (!in_array($token, self::SYMBOLS, true)) {
                    return "«{$token}»";
                }
                continue;
            }
            [$id, $source, $line] = $token;
            if (in_array($id, self::TOKENS, true) || ($id === T_STRING && in_array(strtolower($source), self::WORDS, true))) {
                continue;
            }

            return token_name($id) . ' «' . mb_substr(trim($source), 0, 30) . "» en la línea {$line}";
        }

        return null;
    }
}
