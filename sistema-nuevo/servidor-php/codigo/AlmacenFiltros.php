<?php

declare(strict_types=1);

class AlmacenFiltros
{
    private ConexionBd $bd;

    public function __construct(ConexionBd $bd)
    {
        $this->bd = $bd;
    }

    public function obtenerTodos(): array
    {
        $stmt = $this->bd->conexion()->query('SELECT * FROM filtros_guardados ORDER BY nombre');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->bd->conexion()->prepare('SELECT * FROM filtros_guardados WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(string $nombre, string $scope, ?int $ageMin, ?int $ageMax, ?string $gender, ?string $tipoAccion): int
    {
        $stmt = $this->bd->conexion()->prepare(
            'INSERT INTO filtros_guardados (nombre, scope, age_min, age_max, gender, tipo_accion)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nombre, $scope, $ageMin, $ageMax, $gender, $tipoAccion]);
        return (int)$this->bd->conexion()->lastInsertId();
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->bd->conexion()->prepare('DELETE FROM filtros_guardados WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
