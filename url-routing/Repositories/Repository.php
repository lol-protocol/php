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
     * What a search ignores: accents, tildes and cedillas, in both cases
     * (SQLite's LOWER() leaves non-ASCII capitals alone, so "Á" needs its own
     * entry). The same map runs on the column (sinAcentos()) and on the
     * text (patron()), so "garcia" finds "García" and "GARCÍA" finds "garcia".
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
    ];

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
     */
    protected static function sinAcentos(string $expresion): string
    {
        return "TRANSLATE(LOWER({$expresion}), '" . implode('', array_keys(self::ACENTOS)) . "', '"
            . implode('', self::ACENTOS) . "')";
    }

    /**
     * Case- and accent-insensitive substring pattern for
     * `sinAcentos(col) LIKE ? ESCAPE '!'` (see LIKE_ESCAPED). %, _ and the
     * escape char itself are escaped, so a user typing "%" searches for a
     * percent sign instead of matching every row.
     */
    protected static function patron(string $texto): string
    {
        $texto = mb_strtolower(strtr(trim($texto), self::ACENTOS));
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto) . '%';
    }
}
