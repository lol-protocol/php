<?php

declare(strict_types=1);

class AlmacenFiltros
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function obtenerTodos(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM filtros_guardados ORDER BY nombre');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM filtros_guardados WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(string $nombre, string $scope, ?int $ageMin, ?int $ageMax, ?string $gender, ?string $tipoAccion): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO filtros_guardados (nombre, scope, age_min, age_max, gender, tipo_accion)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nombre, $scope, $ageMin, $ageMax, $gender, $tipoAccion]);
        return (int)$this->pdo->lastInsertId();
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM filtros_guardados WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
