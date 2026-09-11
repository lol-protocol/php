<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Paginacion;
use PDO;

/** CRUD de pagos. El reporting de cobros vive en IngresosRepository. */
final class PagoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Alta manual de un pago. Devuelve el id creado. */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pagos (boleta_id, cliente_id, monto, moneda_codigo, fecha_pago, metodo)
             VALUES (:boleta_id, :cliente_id, :monto, :moneda_codigo, :fecha_pago, :metodo)
             RETURNING id'
        );
        $stmt->execute([
            ':boleta_id' => $datos['boleta_id'] ?: null,
            ':cliente_id' => $datos['cliente_id'],
            ':monto' => $datos['monto'],
            ':moneda_codigo' => $datos['moneda_codigo'],
            ':fecha_pago' => $datos['fecha_pago'],
            ':metodo' => $datos['metodo'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function porId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.nombre AS cliente
             FROM pagos p JOIN clientes c ON c.id = p.cliente_id
             WHERE p.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Edita monto/fecha/metodo. No se puede reasignar el cliente, la boleta ni la moneda. */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            'UPDATE pagos SET monto = :monto, fecha_pago = :fecha_pago, metodo = :metodo WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':monto' => $datos['monto'],
            ':fecha_pago' => $datos['fecha_pago'],
            ':metodo' => $datos['metodo'],
        ]);
    }

    public function anular(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE pagos SET anulada = TRUE WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Listado paginado para la pantalla de Pagos. Incluye los anulados
     * (marcados) para no perder el rastro de la correccion.
     *
     * @return array{filas: array, total: int, totalPaginas: int}
     */
    public function listado(string $desde, string $hasta, ?string $cliente = null, int $pagina = 1): array
    {
        $params = [':desde' => $desde, ':hasta' => $hasta];
        $filtroCliente = '';
        if ($cliente !== null && $cliente !== '') {
            $filtroCliente = ' AND c.nombre ILIKE :cliente';
            $params[':cliente'] = '%' . $cliente . '%';
        }

        $stmtTotal = $this->db->prepare(
            "SELECT COUNT(*) FROM pagos p JOIN clientes c ON c.id = p.cliente_id
             WHERE p.fecha_pago BETWEEN :desde AND :hasta{$filtroCliente}"
        );
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT p.id, p.monto, p.moneda_codigo, p.fecha_pago, p.metodo, p.boleta_id, p.anulada,
                    c.id AS cliente_id, c.nombre AS cliente
             FROM pagos p
             JOIN clientes c ON c.id = p.cliente_id
             WHERE p.fecha_pago BETWEEN :desde AND :hasta{$filtroCliente}
             ORDER BY p.fecha_pago DESC, p.id DESC
             LIMIT :limite OFFSET :offset"
        );
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->bindValue(':limite', Paginacion::POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', Paginacion::offset($pagina), PDO::PARAM_INT);
        $stmt->execute();

        return [
            'filas' => $stmt->fetchAll(),
            'total' => $total,
            'totalPaginas' => Paginacion::totalPaginas($total),
        ];
    }

    /** Todo el historial de pagos de un cliente puntual (para su ficha), sin filtro de fecha. */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, monto, moneda_codigo, fecha_pago, metodo, boleta_id, anulada
             FROM pagos WHERE cliente_id = :id ORDER BY fecha_pago DESC'
        );
        $stmt->execute([':id' => $clienteId]);
        return $stmt->fetchAll();
    }
}
