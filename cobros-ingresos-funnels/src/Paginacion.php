<?php

declare(strict_types=1);

namespace App;

final class Paginacion
{
    public const POR_PAGINA = 25;

    public static function pagina(): int
    {
        return max(1, (int) ($_GET['pagina'] ?? 1));
    }

    public static function offset(int $pagina): int
    {
        return ($pagina - 1) * self::POR_PAGINA;
    }

    public static function totalPaginas(int $totalFilas): int
    {
        return max(1, (int) ceil($totalFilas / self::POR_PAGINA));
    }
}
