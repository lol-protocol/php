<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class MonedaRepository
{
    /** @var array<string, string>|null */
    private static ?array $simbolos = null;

    /** @return array<string, string> codigo => simbolo */
    public static function simbolos(): array
    {
        if (self::$simbolos === null) {
            self::$simbolos = [];
            $rows = Database::connection()->query('SELECT codigo, simbolo FROM monedas')->fetchAll();
            foreach ($rows as $row) {
                self::$simbolos[$row['codigo']] = $row['simbolo'];
            }
        }
        return self::$simbolos;
    }

    public static function simbolo(string $codigo): string
    {
        return self::simbolos()[$codigo] ?? $codigo;
    }
}
