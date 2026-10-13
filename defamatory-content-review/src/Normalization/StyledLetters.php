<?php

namespace DefamatoryContentReview\Normalization;

/**
 * Letras «de adorno» que se ven como el alfabeto latino y que NFKC no
 * siempre deshace: ancho completo («ｐｕｔａ»), alfanuméricos matemáticos
 * («𝐩𝐮𝐭𝐚»), letras en cuadros («🅿🆄🆃🅰»), indicadores regionales
 * («🇵🇺🇹🇦») y versalitas («ᴘᴜᴛᴀ»). Lo usa EvasionCleaner.
 */
final class StyledLetters
{
    private const STYLED = '/[\x{FF01}-\x{FF5E}\x{1D400}-\x{1D6A3}\x{1D7CE}-\x{1D7FF}\x{1F130}-\x{1F189}]/u';
    /** Indicadores regionales sueltos: se ven como letras en cuadros. */
    private const REGIONAL = '/[\x{1F1E6}-\x{1F1FF}]+/u';
    /** Versalitas del alfabeto fonético: no se usan para escribir ninguno de los idiomas del proyecto. */
    private const SMALL_CAPS = [
        'ᴀ' => 'a', 'ʙ' => 'b', 'ᴄ' => 'c', 'ᴅ' => 'd', 'ᴇ' => 'e', 'ꜰ' => 'f', 'ɢ' => 'g', 'ʜ' => 'h', 'ɪ' => 'i',
        'ᴊ' => 'j', 'ᴋ' => 'k', 'ʟ' => 'l', 'ᴍ' => 'm', 'ɴ' => 'n', 'ᴏ' => 'o', 'ᴘ' => 'p', 'ꞯ' => 'q', 'ʀ' => 'r',
        'ꜱ' => 's', 'ᴛ' => 't', 'ᴜ' => 'u', 'ᴠ' => 'v', 'ᴡ' => 'w', 'ʏ' => 'y', 'ᴢ' => 'z',
    ];

    public static function plain(string $text): string
    {
        $text = preg_replace_callback_array([
            self::REGIONAL => fn(array $m): string => ' ' . implode('', array_map(fn(string $c): string => chr(mb_ord($c) - 0x1F1A5), mb_str_split($m[0]))) . ' ',
            self::STYLED => fn(array $m): string => self::unstyle(mb_ord($m[0])),
        ], $text) ?? $text;

        return strtr($text, self::SMALL_CAPS);
    }

    /** Ancho completo, alfanuméricos matemáticos (negrita, cursiva, gótica…) y letras en cuadros («🄿», «🅟», «🅿») a su letra o dígito. */
    private static function unstyle(int $code): string
    {
        return match (true) {
            $code >= 0x1F130 => chr(0x41 + ($code - 0x1F130) % 0x20),
            $code <= 0xFF5E => chr($code - 0xFF01 + 0x21),
            $code >= 0x1D7CE => (string) (($code - 0x1D7CE) % 10),
            default => chr((($code - 0x1D400) % 52) < 26 ? 0x41 + ($code - 0x1D400) % 52 : 0x61 + ($code - 0x1D400) % 52 - 26),
        };
    }
}
