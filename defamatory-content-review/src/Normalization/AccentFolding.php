<?php

namespace DefamatoryContentReview\Normalization;

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

        $text = strtr(self::compose($text), $map);
        if ($language === 'vie' || !class_exists(\Normalizer::class) || preg_match('/[\x{1E00}-\x{1EFF}]/u', $text) !== 1) {
            return $text;
        }

        // Latín extendido adicional («ṗụṭạ»): sólo el vietnamita lo usa para escribir; a la letra base.
        return preg_replace_callback('/[\x{1E00}-\x{1EFF}]/u', function (array $m): string {
            $base = preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($m[0], \Normalizer::FORM_D));

            return is_string($base) && preg_match('/^[a-zA-Z]$/', $base) === 1 ? $base : $m[0];
        }, $text) ?? $text;
    }

    /**
     * «u» + acento combinante («ú» descompuesta, como la escriben algunos teclados
     * y el copiar y pegar) pasa a la «ú» de un carácter que MAP sabe plegar; si
     * no, «pu\u{301}ta» esquivaba el diccionario. Sin la extensión intl se quitan
     * las marcas que siguen a una letra latina, que es lo que MAP haría después.
     */
    private static function compose(string $text): string
    {
        if (!preg_match('/\p{M}/u', $text)) {
            return $text;
        }
        if (class_exists(\Normalizer::class)) {
            return \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        return preg_replace('/(?<=[a-zA-Z])\p{Mn}+/u', '', $text) ?? $text;
    }
}
