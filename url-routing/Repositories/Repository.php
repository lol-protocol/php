<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use App\Support\Privacidad;

abstract class Repository
{
    /** Use with patron(): both SQLite and PostgreSQL honor ESCAPE. */
    protected const LIKE_ESCAPED = " LIKE ? ESCAPE '!'";

    /**
     * What a search ignores: accents, tildes, cedillas and the other marks of the Latin alphabets that
     * surnames use (å ø ł ř š ş ž č ć ğ ę ą ő ű...), in both cases (SQLite's LOWER() leaves non-ASCII
     * capitals alone, so "Á" needs its own entry). The same map runs on the column (sinAcentos()) and on
     * the text (patron()), so "garcia" finds "García" and "GARCÍA" finds "garcia", and "aberg" finds "Åberg".
     *
     * One code point to one ASCII letter, always: TRANSLATE() cannot change a text's length, so ß, æ and œ
     * (which would be "ss", "ae" and "oe") are not here. Every entry makes TRANSLATE() a little slower on
     * every row a search reads (see sinAcentos()).
     */
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c',
        'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ä' => 'a', 'Ã' => 'a',
        'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Ö' => 'o', 'Õ' => 'o',
        'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
        'Ñ' => 'n', 'Ç' => 'c',
        'å' => 'a', 'ą' => 'a', 'ā' => 'a', 'ă' => 'a',
        'ć' => 'c', 'č' => 'c',
        'đ' => 'd', 'ď' => 'd',
        'ę' => 'e', 'ě' => 'e', 'ė' => 'e', 'ē' => 'e',
        'ğ' => 'g',
        'ı' => 'i', 'ī' => 'i',
        'ł' => 'l',
        'ń' => 'n', 'ň' => 'n',
        'ø' => 'o', 'ő' => 'o', 'ō' => 'o',
        'ř' => 'r',
        'ś' => 's', 'š' => 's', 'ş' => 's', 'ș' => 's',
        'ť' => 't', 'ţ' => 't', 'ț' => 't',
        'ů' => 'u', 'ű' => 'u', 'ū' => 'u', 'ų' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ź' => 'z', 'ž' => 'z', 'ż' => 'z',
        'Å' => 'a', 'Ą' => 'a', 'Ā' => 'a', 'Ă' => 'a',
        'Ć' => 'c', 'Č' => 'c',
        'Đ' => 'd', 'Ď' => 'd',
        'Ę' => 'e', 'Ě' => 'e', 'Ė' => 'e', 'Ē' => 'e',
        'Ğ' => 'g',
        'İ' => 'i', 'Ī' => 'i',
        'Ł' => 'l',
        'Ń' => 'n', 'Ň' => 'n',
        'Ø' => 'o', 'Ő' => 'o', 'Ō' => 'o',
        'Ř' => 'r',
        'Ś' => 's', 'Š' => 's', 'Ş' => 's', 'Ș' => 's',
        'Ť' => 't', 'Ţ' => 't', 'Ț' => 't',
        'Ů' => 'u', 'Ű' => 'u', 'Ū' => 'u', 'Ų' => 'u',
        'Ý' => 'y', 'Ÿ' => 'y',
        'Ź' => 'z', 'Ž' => 'z', 'Ż' => 'z',
    ];

    /**
     * The combining marks of the Latin alphabets (grave, acute, circumflex, tilde, macron, breve, dot above,
     * diaeresis, ring, double acute, caron, cedilla, ogonek), U+0300-U+0328: what a text written as "a" plus
     * an accent, instead of the single "á", carries (macOS hands over file names and clipboard text that
     * way). They are erased on both sides of a search, so "Garci\u{0301}a" and "García" are the same name.
     * Only these: the marks of other scripts (Devanagari, Arabic...) are part of their letters.
     */
    private const MARCAS_COMBINANTES = "\u{0300}\u{0301}\u{0302}\u{0303}\u{0304}\u{0306}\u{0307}\u{0308}\u{030A}\u{030B}\u{030C}\u{0327}\u{0328}";

    /**
     * What this repository may reveal about living people. Defaults to what a
     * visitor sees, so a caller that forgets to pass it hides too much rather
     * than too little; the owner's repositories get Privacidad::propietario().
     */
    protected readonly Privacidad $privacidad;

    public function __construct(protected readonly Database $db, ?Privacidad $privacidad = null)
    {
        $this->privacidad = $privacidad ?? Privacidad::publica();
    }

    /**
     * SQL: " AND NOT …" leaving out the $tipo rows (id in $columna) withdrawn after a pedido de retiro,
     * for a visitor's search; '' for the owner, who still sees them (see RetiroRepository).
     */
    protected function sinRetiradas(string $tipo, string $columna): string
    {
        return $this->privacidad->ocultaVivas() ? ' AND NOT ' . Genealogy\RetiroRepository::sqlRetirado($tipo, $columna) : '';
    }

    /**
     * LIMIT/OFFSET are interpolated rather than bound: some drivers type a
     * bound LIMIT as text. Casting to int here is what makes that safe.
     */
    protected static function page(int $limit, int $offset = 0): string
    {
        return ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    }

    /**
     * SQL: $expresion lowercased and without accents (see ACENTOS), to search
     * with `sinAcentos(col) LIKE ? ESCAPE '!'` and patron(). TRANSLATE() is
     * PostgreSQL's; on SQLite, Database registers the same function. $expresion
     * comes from the repository's own code, never from a request.
     *
     * The combining marks go at the end of the "from" list with nothing to become: TRANSLATE() deletes the
     * characters of "from" that have no counterpart in "to", on both engines.
     *
     * TRANSLATE() is the expensive part: PostgreSQL walks the whole "from" list for every character of every
     * row it reads (4 µs a row with the 48 entries this had, 10 µs with today's 137). No entry of the list is
     * ASCII, so for a text that is all ASCII it changes nothing, and that is most of the rows: a text whose
     * length in bytes equals its length in characters is only lowercased (0.4 µs a row). Each entry added
     * to ACENTOS only costs the rows that have accents.
     */
    protected static function sinAcentos(string $expresion): string
    {
        $traducida = "TRANSLATE(LOWER({$expresion}), '" . implode('', array_keys(self::ACENTOS)) . self::MARCAS_COMBINANTES . "', '"
            . implode('', self::ACENTOS) . "')";

        return "(CASE WHEN octet_length({$expresion}) = length({$expresion}) THEN LOWER({$expresion}) ELSE {$traducida} END)";
    }

    /**
     * Case- and accent-insensitive substring pattern for
     * `sinAcentos(col) LIKE ? ESCAPE '!'` (see LIKE_ESCAPED). %, _ and the
     * escape char itself are escaped, so a user typing "%" searches for a
     * percent sign instead of matching every row.
     */
    protected static function patron(string $texto): string
    {
        $texto = mb_strtolower(strtr(trim($texto), self::ACENTOS + array_fill_keys(mb_str_split(self::MARCAS_COMBINANTES), '')));
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto) . '%';
    }
}
