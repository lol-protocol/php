<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class PaisRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Para poblar selects: codigo, nombre y la moneda que le corresponde. */
    public function listado(): array
    {
        return $this->db->query(
            'SELECT p.codigo, p.nombre, p.moneda_codigo
             FROM paises p
             ORDER BY p.nombre'
        )->fetchAll();
    }
}
