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

    public function porId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios_sistema WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array lista de usuarios (sin password_hash), ordenados por nombre. */
    public function listado(): array
    {
        return $this->db->query(
            'SELECT id, nombre, email, activo, creado_en FROM usuarios_sistema ORDER BY nombre'
        )->fetchAll();
    }

    /** Alta manual de un usuario del sistema. Devuelve el id creado. */
    public function crear(string $nombre, string $email, string $password): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios_sistema (nombre, email, password_hash) VALUES (:nombre, :email, :hash) RETURNING id'
        );
        $stmt->execute([
            ':nombre' => $nombre,
            ':email' => $email,
            ':hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function cambiarPassword(int $id, string $passwordNueva): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios_sistema SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':id' => $id, ':hash' => password_hash($passwordNueva, PASSWORD_DEFAULT)]);
    }

    /** Prende o apaga el acceso de un usuario (soft-delete). Devuelve el nuevo estado. */
    public function alternarActivo(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios_sistema SET activo = NOT activo WHERE id = :id RETURNING activo'
        );
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }
}
