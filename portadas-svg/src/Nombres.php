<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Comparar y convertir nombres escritos a mano: sin mayúsculas, sin tildes y sin espacios de más. */
final class Nombres
{
    /** Letras con marca que no se descomponen solas, y a qué letra base se parecen. */
    private const BASE = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd', 'ð' => 'd',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ı' => 'i',
        'ł' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o',
        'ř' => 'r', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ť' => 't',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ž' => 'z', 'ż' => 'z',
        'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss', 'þ' => 'th',
    ];

    /** Minúsculas, sin tildes y con un solo espacio entre palabras: para saber si dos nombres son el mismo. */
    public static function plegar(string $texto): string
    {
        $minusculas = strtr(mb_strtolower($texto), self::BASE);
        $sinMarcas = preg_replace('/\p{Mn}+/u', '', $minusculas) ?? $minusculas;

        return trim(preg_replace('/\s+/u', ' ', $sinMarcas) ?? $sinMarcas);
    }

    /**
     * Nombre de archivo o carpeta: solo letras a-z, cifras y guiones, de hasta `$largo` caracteres (se
     * corta en un guion, no a media palabra). Vacío si el texto no tiene ninguna letra latina ni cifra.
     */
    public static function slug(string $texto, int $largo = 80): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', self::plegar($texto)) ?? '', '-');
        if (strlen($slug) <= $largo) {
            return $slug;
        }
        $corte = strrpos(substr($slug, 0, $largo + 1), '-');

        return substr($slug, 0, $corte === false || $corte === 0 ? $largo : $corte);
    }
}
