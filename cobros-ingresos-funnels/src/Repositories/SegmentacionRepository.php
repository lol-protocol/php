<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Segmentacion de clientes por facturacion (pais/ciudad/idioma/genero/edad) y
 * LTV por cohorte. Separado de ClienteRepository porque responde "quien nos
 * genera mas ingresos" en vez de "leer/escribir un cliente".
 */
final class SegmentacionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function topPorPais(int $limite = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.nombre AS etiqueta,
                    COUNT(DISTINCT c.id) AS clientes,
                    COALESCE(SUM(b.monto * m.tasa_a_usd), 0) AS total_facturado
             FROM clientes c
             JOIN paises p ON p.codigo = c.pais_codigo
             JOIN boletas b ON b.cliente_id = c.id AND NOT b.anulada
             JOIN monedas m ON m.codigo = b.moneda_codigo
             GROUP BY p.nombre
             ORDER BY total_facturado DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
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
                WHEN (CURRENT_DATE - c.fecha_nacimiento) / 365.25 < 25 THEN '18-24'
                WHEN (CURRENT_DATE - c.fecha_nacimiento) / 365.25 < 35 THEN '25-34'
                WHEN (CURRENT_DATE - c.fecha_nacimiento) / 365.25 < 45 THEN '35-44'
                WHEN (CURRENT_DATE - c.fecha_nacimiento) / 365.25 < 55 THEN '45-54'
                WHEN (CURRENT_DATE - c.fecha_nacimiento) / 365.25 < 65 THEN '55-64'
                ELSE '65+'
             END",
            $limite
        );
    }

    /**
     * Agrupa clientes por una dimension (ciudad, idioma, genero, rango de
     * edad, etc.) y los ordena por facturacion total en USD, de mayor a menor.
     * $expresionSql siempre es una expresion fija definida en este archivo,
     * nunca entrada de usuario.
     */
    private function topPorDimension(string $expresionSql, int $limite): array
    {
        $stmt = $this->db->prepare(
            "SELECT {$expresionSql} AS etiqueta,
                    COUNT(DISTINCT c.id) AS clientes,
                    COALESCE(SUM(b.monto * m.tasa_a_usd), 0) AS total_facturado
             FROM clientes c
             JOIN boletas b ON b.cliente_id = c.id AND NOT b.anulada
             JOIN monedas m ON m.codigo = b.moneda_codigo
             GROUP BY etiqueta
             ORDER BY total_facturado DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * LTV (a la fecha) por cohorte de alta: para cada mes en que un grupo de
     * clientes se dio de alta, el promedio de cuanto pago cada uno hasta hoy.
     */
    public function ltvPorCohorte(): array
    {
        return $this->db->query(
            "SELECT cohorte, COUNT(*) AS clientes, AVG(total_cliente) AS ltv_promedio
             FROM (
                 SELECT to_char(c.fecha_alta, 'YYYY-MM') AS cohorte, c.id,
                        COALESCE(SUM(p.monto * m.tasa_a_usd), 0) AS total_cliente
                 FROM clientes c
                 LEFT JOIN pagos p ON p.cliente_id = c.id AND NOT p.anulada
                 LEFT JOIN monedas m ON m.codigo = p.moneda_codigo
                 GROUP BY cohorte, c.id
             ) sub
             GROUP BY cohorte
             ORDER BY cohorte"
        )->fetchAll();
    }
}
