<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

abstract class Repository
{
    /** Use with patron(): both SQLite and PostgreSQL honor ESCAPE. */
    protected const LIKE_ESCAPED = " LIKE ? ESCAPE '!'";

    public function __construct(protected readonly Database $db)
    {
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
     * Case-insensitive substring pattern for `LOWER(col) LIKE ? ESCAPE '!'`
     * (see LIKE_ESCAPED). %, _ and the escape char itself are escaped, so a
     * user typing "%" searches for a percent sign instead of matching
     * every row.
     */
    protected static function patron(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto) . '%';
    }
}
