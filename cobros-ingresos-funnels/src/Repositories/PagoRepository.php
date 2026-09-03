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

    public function cobrosPorMes(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT strftime('%Y-%m', fecha_pago) AS mes, SUM(monto) AS total
             FROM pagos
             WHERE fecha_pago BETWEEN :desde AND :hasta
             GROUP BY mes ORDER BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    public function porMetodo(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT metodo, SUM(monto) AS total, COUNT(*) AS cantidad
             FROM pagos
             WHERE fecha_pago BETWEEN :desde AND :hasta
             GROUP BY metodo ORDER BY total DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    public function listado(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.monto, p.fecha_pago, p.metodo, p.boleta_id,
                    c.nombre AS cliente
             FROM pagos p
             JOIN clientes c ON c.id = p.cliente_id
             WHERE p.fecha_pago BETWEEN :desde AND :hasta
             ORDER BY p.fecha_pago DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }
}
