<?php

namespace DefamatoryContentReview;

/**
 * Palabras cotidianas que, escritas tal cual, nunca se marcan en el chat
 * aunque al quitarles las tildes coincidan con un insulto: «moc» (checo,
 * «mucho») no es «moč» (orina), «sık» (turco, «frecuente») no es «sik».
 * Se comparan sin plegar, en minúsculas, y como palabra entera: «moč» y
 * «m0c» se siguen marcando. Colaborador interno de ChatTopics.
 */
final class EverydayWords
{
    /** Palabra entera, con guiones o apóstrofos internos: «ver-me», «l'idiot». */
    private const WORD = '/(?<![\p{L}\p{N}])[\p{L}\p{N}]+(?:[\-\'’][\p{L}\p{N}]+)*(?![\p{L}\p{N}])/u';

    /**
     * El texto con cada palabra cotidiana tapada por espacios del mismo largo,
     * para que el diccionario no la vea y las demás posiciones no cambien.
     *
     * @param array<string,true> $everyday claves en minúsculas
     */
    public static function mask(string $text, array $everyday): string
    {
        if ($everyday === []) {
            return $text;
        }

        return preg_replace_callback(
            self::WORD,
            fn(array $word): string => isset($everyday[mb_strtolower($word[0])]) ? str_repeat(' ', mb_strlen($word[0])) : $word[0],
            $text
        ) ?? $text;
    }
}
