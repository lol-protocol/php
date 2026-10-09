<?php

namespace DefamatoryContentReview;

/**
 * Plegado de diacríticos compartido por WordList::normalize(), para que
 * "Cérda", "cerda" y variantes con distinto acento lleguen a la misma
 * clave de búsqueda. Cubre los diacríticos latinos de los 33 idiomas del
 * proyecto, no sólo los del español.
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
     * Letras que en el MAP de arriba son variante diacrítica de otra en la
     * mayoría de idiomas, pero en estos son letra propia del alfabeto, y
     * plegarlas fusiona palabras no emparentadas. Medido contra las 50.000
     * palabras más usadas de cada idioma (bin/false-positives.php):
     * - vie: ă â ê ô đ y los tonos son fonemas («dài» = largo, «dái» = testículos;
     *   «đeo» = llevar puesto, «đéo» = vulgar);
     * - dan/nor: æ ø å («høre» = oír, «hore» = prostituta; «når» = cuando, «nar» = tonto);
     * - swe: å ä ö («höra» = oír, «hora»; «rätta» = corregir, «råtta» = rata);
     * - fin: å ä ö («tai» = o, la 56.ª palabra más usada; «täi» = piojo).
     */
    private const LANGUAGE_EXCLUSIONS = [
        'vie' => ['ă', 'â', 'ê', 'ô', 'đ', 'á', 'à', 'ã', 'é', 'è', 'í', 'ì', 'ó', 'ò', 'õ', 'ú', 'ù', 'ý'],
        'dan' => ['æ', 'ø', 'å'],
        'nor' => ['æ', 'ø', 'å'],
        'swe' => ['å', 'ä', 'ö'],
        'fin' => ['å', 'ä', 'ö'],
    ];

    public static function fold(string $text, string $language = ''): string
    {
        $map = self::MAP;
        foreach (self::LANGUAGE_EXCLUSIONS[$language] ?? [] as $letter) {
            unset($map[$letter]);
        }

        return strtr($text, $map);
    }
}
