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

    /** true si $codigo es el codigo de un pais del catalogo (el formulario solo ofrece esos). */
    public function existe(string $codigo): bool
    {
        $stmt = $this->db->prepare('SELECT EXISTS (SELECT 1 FROM paises WHERE codigo = :codigo)');
        $stmt->execute([':codigo' => $codigo]);

        return (bool) $stmt->fetchColumn();
    }
}
