<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

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

    /** Todo el historial de pagos de un cliente puntual (para su ficha), sin filtro de fecha. */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, monto, moneda_codigo, fecha_pago, metodo, boleta_id
             FROM pagos WHERE cliente_id = :id ORDER BY fecha_pago DESC'
        );
        $stmt->execute([':id' => $clienteId]);
        return $stmt->fetchAll();
    }

    /** Cobros por mes, consolidados a USD. */
    public function cobrosPorMes(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(p.fecha_pago, 'YYYY-MM') AS mes, SUM(p.monto * m.tasa_a_usd) AS total
             FROM pagos p
             JOIN monedas m ON m.codigo = p.moneda_codigo
             WHERE p.fecha_pago BETWEEN :desde AND :hasta
             GROUP BY mes ORDER BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    /** Total por metodo de pago, consolidado a USD. */
    public function porMetodo(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.metodo, SUM(p.monto * m.tasa_a_usd) AS total, COUNT(*) AS cantidad
             FROM pagos p
             JOIN monedas m ON m.codigo = p.moneda_codigo
             WHERE p.fecha_pago BETWEEN :desde AND :hasta
             GROUP BY p.metodo ORDER BY total DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    public function listado(string $desde, string $hasta, ?string $cliente = null): array
    {
        $params = [':desde' => $desde, ':hasta' => $hasta];
        $filtroCliente = '';
        if ($cliente !== null && $cliente !== '') {
            $filtroCliente = ' AND c.nombre ILIKE :cliente';
            $params[':cliente'] = '%' . $cliente . '%';
        }

        $stmt = $this->db->prepare(
            "SELECT p.id, p.monto, p.moneda_codigo, p.fecha_pago, p.metodo, p.boleta_id,
                    c.id AS cliente_id, c.nombre AS cliente
             FROM pagos p
             JOIN clientes c ON c.id = p.cliente_id
             WHERE p.fecha_pago BETWEEN :desde AND :hasta{$filtroCliente}
             ORDER BY p.fecha_pago DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
