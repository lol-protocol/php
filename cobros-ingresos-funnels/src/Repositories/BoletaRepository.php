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
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $calculo = EstadoBoleta::calcular(
                (float) $row['monto'],
                (float) $row['pagado'],
                $row['fecha_vencimiento'],
                null,
                (bool) $row['anulada']
            );
            $row['saldo'] = $calculo['saldo'];
            $row['estado'] = $calculo['estado'];
        }

        return $rows;
    }

    /**
     * Listado paginado para la pantalla de Cobros. Incluye anuladas (con su
     * badge) salvo que se filtre explicitamente por otro estado.
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

        $stmt = $this->db->prepare(
            "SELECT b.id, b.concepto, b.monto, b.moneda_codigo, b.fecha_emision, b.fecha_vencimiento, b.anulada,
                    c.id AS cliente_id, c.nombre AS cliente,
                    COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada), 0) AS pagado
             FROM boletas b
             JOIN clientes c ON c.id = b.cliente_id
             WHERE b.fecha_emision BETWEEN :desde AND :hasta{$filtroCliente}
             ORDER BY b.fecha_emision DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $filtradas = [];
        foreach ($rows as $row) {
            $calculo = EstadoBoleta::calcular(
                (float) $row['monto'],
                (float) $row['pagado'],
                $row['fecha_vencimiento'],
                null,
                (bool) $row['anulada']
            );

            if ($estado !== null && $estado !== '' && $calculo['estado'] !== $estado) {
                continue;
            }

            $row['saldo'] = $calculo['saldo'];
            $row['estado'] = $calculo['estado'];
            $filtradas[] = $row;
        }

        $total = count($filtradas);

        return [
            'filas' => array_slice($filtradas, Paginacion::offset($pagina), Paginacion::POR_PAGINA),
            'total' => $total,
            'totalPaginas' => Paginacion::totalPaginas($total),
        ];
    }
}
