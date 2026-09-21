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

    /**
     * La pagina pedida, recortada a las que realmente existen. Sin esto,
     * "?pagina=5000" sobre cuatro paginas de datos devolvia una tabla vacia
     * rotulada "Pagina 5000 de 4" (y sin link util para volver, porque
     * Anterior apuntaba a la 4999), y "?pagina=9999999999999999999" hacia
     * desbordar el entero: ($pagina - 1) * POR_PAGINA pasaba a float y
     * reventaba con un 500 en las cuatro pantallas paginadas.
     */
    public static function acotar(int $pagina, int $totalFilas): int
    {
        return min(max(1, $pagina), self::totalPaginas($totalFilas));
    }

    public static function totalPaginas(int $totalFilas): int
    {
        return max(1, (int) ceil($totalFilas / self::POR_PAGINA));
    }
}
