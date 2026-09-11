<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\EstadoBoleta;
use App\Paginacion;
use PDO;

/** CRUD de boletas. El reporting de ingresos vive en IngresosRepository. */
final class BoletaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Alta manual de una boleta. Devuelve el id creado. */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO boletas (cliente_id, concepto, monto, moneda_codigo, fecha_emision, fecha_vencimiento)
             VALUES (:cliente_id, :concepto, :monto, :moneda_codigo, :fecha_emision, :fecha_vencimiento)
             RETURNING id'
        );
        $stmt->execute([
            ':cliente_id' => $datos['cliente_id'],
            ':concepto' => $datos['concepto'],
            ':monto' => $datos['monto'],
            ':moneda_codigo' => $datos['moneda_codigo'],
            ':fecha_emision' => $datos['fecha_emision'],
            ':fecha_vencimiento' => $datos['fecha_vencimiento'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function porId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT b.*, c.nombre AS cliente
             FROM boletas b JOIN clientes c ON c.id = b.cliente_id
             WHERE b.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Edita concepto/monto/fechas. No se puede reasignar el cliente ni la moneda. */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            'UPDATE boletas SET concepto = :concepto, monto = :monto,
                fecha_emision = :fecha_emision, fecha_vencimiento = :fecha_vencimiento
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':concepto' => $datos['concepto'],
            ':monto' => $datos['monto'],
            ':fecha_emision' => $datos['fecha_emision'],
            ':fecha_vencimiento' => $datos['fecha_vencimiento'],
        ]);
    }

    public function anular(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE boletas SET anulada = TRUE WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Todo el historial de boletas de un cliente puntual (para su ficha), sin
     * filtro de fecha. Incluye las anuladas (quedan marcadas, no se ocultan).
     */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.id, b.concepto, b.monto, b.moneda_codigo, b.fecha_emision, b.fecha_vencimiento, b.anulada,
                    COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada), 0) AS pagado
             FROM boletas b
             WHERE b.cliente_id = :id
             ORDER BY b.fecha_emision DESC"
        );
        $stmt->execute([':id' => $clienteId]);

        return self::conEstadoCalculado($stmt->fetchAll());
    }

    /**
     * Listado paginado para la pantalla de Cobros. Incluye anuladas (con su
     * badge) salvo que se filtre explicitamente por otro estado.
     *
     * El estado (pagada/parcial/pendiente/vencida/anulada) no se guarda en la
     * base, se calcula en PHP a partir de los pagos aplicados (ver
     * EstadoBoleta). Sin filtro de estado eso no afecta que filas entran, asi
     * que se pagina en SQL con LIMIT/OFFSET igual que PagoRepository. Con
     * filtro de estado no hay forma de paginar en SQL sin duplicar esa logica
     * en una expresion CASE, asi que ese camino trae el rango completo, lo
     * filtra en PHP y recien ahi pagina con array_slice.
     *
     * @return array{filas: array, total: int, totalPaginas: int}
     */
    public function listado(string $desde, string $hasta, ?string $estado = null, ?string $cliente = null, int $pagina = 1): array
    {
        $params = [':desde' => $desde, ':hasta' => $hasta];
        $filtroCliente = '';
        if ($cliente !== null && $cliente !== '') {
            $filtroCliente = ' AND c.nombre ILIKE :cliente';
            $params[':cliente'] = '%' . $cliente . '%';
        }

        $select = "SELECT b.id, b.concepto, b.monto, b.moneda_codigo, b.fecha_emision, b.fecha_vencimiento, b.anulada,
                    c.id AS cliente_id, c.nombre AS cliente,
                    COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada), 0) AS pagado
             FROM boletas b
             JOIN clientes c ON c.id = b.cliente_id
             WHERE b.fecha_emision BETWEEN :desde AND :hasta{$filtroCliente}";

        if ($estado === null || $estado === '') {
            $stmtTotal = $this->db->prepare(
                "SELECT COUNT(*) FROM boletas b JOIN clientes c ON c.id = b.cliente_id
                 WHERE b.fecha_emision BETWEEN :desde AND :hasta{$filtroCliente}"
            );
            $stmtTotal->execute($params);
            $total = (int) $stmtTotal->fetchColumn();

            $stmt = $this->db->prepare("{$select} ORDER BY b.fecha_emision DESC, b.id DESC LIMIT :limite OFFSET :offset");
            foreach ($params as $clave => $valor) {
                $stmt->bindValue($clave, $valor);
            }
            $stmt->bindValue(':limite', Paginacion::POR_PAGINA, PDO::PARAM_INT);
            $stmt->bindValue(':offset', Paginacion::offset($pagina), PDO::PARAM_INT);
            $stmt->execute();

            return [
                'filas' => self::conEstadoCalculado($stmt->fetchAll()),
                'total' => $total,
                'totalPaginas' => Paginacion::totalPaginas($total),
            ];
        }

        $stmt = $this->db->prepare("{$select} ORDER BY b.fecha_emision DESC");
        $stmt->execute($params);
        $filtradas = array_filter(
            self::conEstadoCalculado($stmt->fetchAll()),
            static fn (array $fila): bool => $fila['estado'] === $estado
        );
        $total = count($filtradas);

        return [
            'filas' => array_slice($filtradas, Paginacion::offset($pagina), Paginacion::POR_PAGINA),
            'total' => $total,
            'totalPaginas' => Paginacion::totalPaginas($total),
        ];
    }

    /** Agrega saldo/estado calculado (EstadoBoleta) a cada fila de un fetchAll() de boletas. */
    private static function conEstadoCalculado(array $filas): array
    {
        foreach ($filas as &$fila) {
            $calculo = EstadoBoleta::calcular(
                (float) $fila['monto'],
                (float) $fila['pagado'],
                $fila['fecha_vencimiento'],
                null,
                (bool) $fila['anulada']
            );
            $fila['saldo'] = $calculo['saldo'];
            $fila['estado'] = $calculo['estado'];
        }

        return $filas;
    }
}
