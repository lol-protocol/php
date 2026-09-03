<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class ClienteRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function topPorFacturacion(int $limite = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.nombre, c.segmento, SUM(f.monto) AS total_facturado
             FROM clientes c
             JOIN facturas f ON f.cliente_id = c.id
             GROUP BY c.id
             ORDER BY total_facturado DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function total(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
    }
}
