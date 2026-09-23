<?php

namespace DefamatoryContentReview;

/**
 * Plegado de diacríticos compartido por WordList::normalize(), para que
 * "Cérda", "cerda" y variantes con distinto acento lleguen a la misma
 * clave de búsqueda. Cubre los diacríticos latinos de los 33 idiomas del
 * proyecto, no sólo los del español.
 *
 * El mapa sólo alcanza a los caracteres PRECOMPUESTOS (NFC). Un mismo
 * nombre escrito en NFD —la vocal y su acento como dos caracteres— no
 * produce ninguna de esas claves, y antes evadía el filtro entero: iOS y
 * buena parte de los registros genealógicos heredados emiten NFD. Por eso
 * se quitan además las marcas combinantes, que es lo mismo que el mapa
 * hace con la forma precompuesta.
 */
final class AccentFolding
{
    private const MAP = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ī' => 'i', 'į' => 'i', 'ı' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c',
        'ś' => 's', 'š' => 's', 'ş' => 's',
        'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        'ł' => 'l', 'ĺ' => 'l', 'ľ' => 'l',
        'ř' => 'r', 'ŕ' => 'r',
        'ť' => 't', 'ţ' => 't',
        'ď' => 'd', 'đ' => 'd',
        'ğ' => 'g',
        'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
    ];

    /**
     * Marcas combinantes latinas/griegas/cirílicas (U+0300–U+036F). El rango
     * se acota a propósito en vez de usar \p{Mn}: los signos del tailandés,
     * el árabe, el hebreo o el devanagari también son marcas sin espaciado,
     * pero ahí distinguen palabras distintas y quitarlos las colapsaría.
     */
    private const COMBINING_RANGE = '/[\x{0300}-\x{036F}]/u';

    public static function fold(string $text): string
    {
        return self::stripCombiningMarks(strtr($text, self::MAP));
    }

    /**
     * Quita las marcas combinantes que el mapa no puede alcanzar.
     *
     * Cubre dos casos que llegaban intactos al índice: la entrada en NFD, y
     * la "İ" turca —cuya minúscula es "i" más U+0307, sin forma
     * precompuesta a la que normalizar—, que dejaba pasar cualquier término
     * turco escrito con ella.
     */
    public static function stripCombiningMarks(string $text): string
    {
        return preg_replace(self::COMBINING_RANGE, '', $text) ?? $text;
    }
}
