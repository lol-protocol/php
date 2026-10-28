<?php

declare(strict_types=1);

namespace App;

use Generator;
use PDO;

/**
 * @phpstan-type Pagina array{filas: array, total: int, totalPaginas: int, pagina: int}
 */
final class Paginacion
{
    public const POR_PAGINA = 25;

    /** Filas que trae cada FETCH de recorrer(): lo que se tiene en memoria a la vez, sin importar cuantas filas haya. */
    public const POR_LOTE = 1000;

    private static int $recorridos = 0;

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

    /**
     * Una pagina de un listado: cuenta las filas de la consulta, acota la pagina
     * pedida a las que existen y trae solo las filas de esa pagina. Es el esqueleto
     * que repetian BoletaRepository::listado(), ClienteRepository::buscar() y
     * PagoRepository::listado(); lo propio de cada uno (columnas, tablas, filtros)
     * queda en su repositorio.
     *
     * $desde es lo que va despues de FROM: tablas, joins y el WHERE, con los
     * :marcadores de $params. $desdeDelConteo es lo mismo para el COUNT cuando
     * alcanza algo mas barato (la tabla en vez de la vista que suma los pagos, o
     * sin un join que no cambia la cantidad); sin el se cuenta sobre $desde.
     * $orden es el ORDER BY y tiene que dar un orden total, con desempate por id:
     * si no, dos filas empatadas pueden cambiar de lugar entre una pagina y la
     * siguiente.
     *
     * @param array<string, string> $params los marcadores de $desde (y de $desdeDelConteo)
     * @return Pagina
     */
    public static function consultar(
        PDO $db,
        string $columnas,
        string $desde,
        string $orden,
        array $params,
        int $pagina,
        ?string $desdeDelConteo = null,
    ): array {
        $stmtTotal = $db->prepare('SELECT COUNT(*) FROM ' . ($desdeDelConteo ?? $desde));
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();
        $pagina = self::acotar($pagina, $total);

        $stmt = $db->prepare("SELECT {$columnas} FROM {$desde} ORDER BY {$orden} LIMIT :limite OFFSET :offset");
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->bindValue(':limite', self::POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', self::offset($pagina), PDO::PARAM_INT);
        $stmt->execute();

        return [
            'filas' => $stmt->fetchAll(),
            'total' => $total,
            'totalPaginas' => self::totalPaginas($total),
            'pagina' => $pagina,
        ];
    }

    /**
     * TODAS las filas de la misma consulta que consultar() pagina (mismos $columnas,
     * $desde, $orden y $params), de a una y sin tenerlas juntas en memoria: es lo que
     * usa una exportacion, que no puede cortar en paginas. Con un cursor de PostgreSQL
     * trae $lote filas por vez; exportar 120 mil boletas con fetchAll() era
     * reventar el limite de memoria de PHP, como le paso al filtro de estado de Cobros.
     *
     * Un cursor solo vive dentro de una transaccion: si no hay una abierta, se abre
     * una (solo de lectura, y se deshace al terminar) y, si ya hay una, se usa esa. Se
     * cierra al terminar de recorrer y tambien si quien lo recorre se corta antes
     * (un break, una excepcion).
     *
     * @param array<string, string> $params los marcadores de $desde
     * @return Generator<int, array<string, mixed>>
     */
    public static function recorrer(
        PDO $db,
        string $columnas,
        string $desde,
        string $orden,
        array $params,
        int $lote = self::POR_LOTE,
    ): Generator {
        $propia = !$db->inTransaction();
        if ($propia) {
            $db->beginTransaction();
        }
        $cursor = 'recorrido_' . (++self::$recorridos);

        try {
            $declarar = $db->prepare("DECLARE {$cursor} NO SCROLL CURSOR FOR SELECT {$columnas} FROM {$desde} ORDER BY {$orden}");
            $declarar->execute($params);
            while (($filas = $db->query("FETCH FORWARD {$lote} FROM {$cursor}")->fetchAll()) !== []) {
                yield from $filas;
            }
        } finally {
            if ($propia) {
                // Terminar la transaccion cierra el cursor, y no falla aunque la consulta si lo haya hecho.
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
            } else {
                // Es de una transaccion de afuera: se cierra solo el cursor. Si esa transaccion
                // ya quedo abortada por un error, no se puede ni cerrar, y se deja que la deshaga quien la abrio.
                try {
                    $db->exec("CLOSE {$cursor}");
                } catch (\PDOException) {
                }
            }
        }
    }
}
