<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class UsuarioSistemaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function porEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios_sistema WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
