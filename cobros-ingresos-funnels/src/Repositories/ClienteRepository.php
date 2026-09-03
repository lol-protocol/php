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

    public function total(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
    }

    public function topPorPais(int $limite = 5): array
    {
        return $this->topPorDimension('c.pais', $limite);
    }

    public function topPorCiudad(int $limite = 5): array
    {
        return $this->topPorDimension('c.ciudad', $limite);
    }

    public function topPorIdioma(int $limite = 5): array
    {
        return $this->topPorDimension('c.idioma', $limite);
    }

    public function topPorGenero(int $limite = 5): array
    {
        return $this->topPorDimension('c.genero', $limite);
    }

    public function topPorRangoEdad(int $limite = 5): array
    {
        return $this->topPorDimension(
            "CASE
                WHEN (julianday('now') - julianday(c.fecha_nacimiento)) / 365.25 < 25 THEN '18-24'
                WHEN (julianday('now') - julianday(c.fecha_nacimiento)) / 365.25 < 35 THEN '25-34'
                WHEN (julianday('now') - julianday(c.fecha_nacimiento)) / 365.25 < 45 THEN '35-44'
                WHEN (julianday('now') - julianday(c.fecha_nacimiento)) / 365.25 < 55 THEN '45-54'
                WHEN (julianday('now') - julianday(c.fecha_nacimiento)) / 365.25 < 65 THEN '55-64'
                ELSE '65+'
             END",
            $limite
        );
    }

    /**
     * Agrupa clientes por una dimension (pais, ciudad, idioma, genero, rango de
     * edad, etc.) y los ordena por facturacion total, de mayor a menor.
     * $expresionSql siempre es una expresion fija definida en este archivo,
     * nunca entrada de usuario.
     */
    private function topPorDimension(string $expresionSql, int $limite): array
    {
        $stmt = $this->db->prepare(
            "SELECT {$expresionSql} AS etiqueta,
                    COUNT(DISTINCT c.id) AS clientes,
                    COALESCE(SUM(b.monto), 0) AS total_facturado
             FROM clientes c
             JOIN boletas b ON b.cliente_id = c.id
             GROUP BY etiqueta
             ORDER BY total_facturado DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
