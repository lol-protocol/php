<?php

declare(strict_types=1);

/** Credenciales de los administradores del panel (login). */
final class AlmacenAdministradores
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Hash bcrypt del usuario dado, o null si no existe. */
    public function claveHash(string $usuario): ?string
    {
        $stmt = $this->pdo->prepare('SELECT clave_hash FROM administradores WHERE usuario = ?');
        $stmt->execute([$usuario]);
        $valor = $stmt->fetchColumn();
        return $valor === false ? null : $valor;
    }
}
