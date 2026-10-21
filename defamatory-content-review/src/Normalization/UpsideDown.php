<?php

namespace DefamatoryContentReview\Normalization;

/**
 * Texto cabeza abajo: «ɐʇnd», «ǝʇoᴉpᴉ sǝ». Cada palabra con alguna letra que
 * sólo se usa para eso se lee al revés y con cada letra derecha; las demás
 * palabras no se tocan. Lo usa EvasionCleaner.
 */
final class UpsideDown
{
    /** Letras que delatan el truco: no se escriben en ninguno de los idiomas del proyecto. */
    private const TELL = '/[ɐǝɟƃɥᴉɾʞɯɹʇʍʎ]/u';
    private const TURNED = [
        'ɐ' => 'a', 'q' => 'b', 'ɔ' => 'c', 'p' => 'd', 'ǝ' => 'e', 'ɟ' => 'f', 'ƃ' => 'g', 'ɥ' => 'h', 'ᴉ' => 'i',
        'ı' => 'i', 'ɾ' => 'j', 'ʞ' => 'k', 'ɯ' => 'm', 'u' => 'n', 'd' => 'p', 'b' => 'q', 'ɹ' => 'r', 'ʇ' => 't',
        'n' => 'u', 'ʌ' => 'v', 'ʍ' => 'w', 'ʎ' => 'y', '¡' => '!', '¿' => '?', '˙' => '.', '\'' => ',', ',' => '\'',
    ];

    public static function flip(string $text): string
    {
        if (preg_match(self::TELL, $text) !== 1) {
            return $text;
        }

        return preg_replace_callback('/\S+/u', fn(array $m): string => preg_match(self::TELL, $m[0]) === 1
            ? strtr(implode('', array_reverse(mb_str_split($m[0]))), self::TURNED) : $m[0], $text) ?? $text;
    }
}
