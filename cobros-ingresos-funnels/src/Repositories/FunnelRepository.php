<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class FunnelRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Conteo de usuarios en cada etapa del funnel dentro del rango de fecha_visita. */
    public function resumenEtapas(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS visitantes,
                SUM(CASE WHEN fecha_registro IS NOT NULL THEN 1 ELSE 0 END) AS registrados,
                SUM(CASE WHEN fecha_lead IS NOT NULL THEN 1 ELSE 0 END) AS leads,
                SUM(CASE WHEN fecha_conversion IS NOT NULL THEN 1 ELSE 0 END) AS clientes
             FROM usuarios_funnel
             WHERE fecha_visita BETWEEN :desde AND :hasta"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        $row = $stmt->fetch();

        return [
            'visitantes' => (int) $row['visitantes'],
            'registrados' => (int) $row['registrados'],
            'leads' => (int) $row['leads'],
            'clientes' => (int) $row['clientes'],
        ];
    }

    public function porCanal(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT canal,
                    COUNT(*) AS visitantes,
                    SUM(CASE WHEN fecha_registro IS NOT NULL THEN 1 ELSE 0 END) AS registrados,
                    SUM(CASE WHEN fecha_lead IS NOT NULL THEN 1 ELSE 0 END) AS leads,
                    SUM(CASE WHEN fecha_conversion IS NOT NULL THEN 1 ELSE 0 END) AS clientes
             FROM usuarios_funnel
             WHERE fecha_visita BETWEEN :desde AND :hasta
             GROUP BY canal ORDER BY visitantes DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    public function tiempoPromedioConversionDias(): float
    {
        $valor = $this->db->query(
            "SELECT AVG(julianday(fecha_conversion) - julianday(fecha_visita))
             FROM usuarios_funnel WHERE fecha_conversion IS NOT NULL"
        )->fetchColumn();

        return $valor !== null ? round((float) $valor, 1) : 0.0;
    }

    /** Visitantes y conversiones por mes, para la tendencia del funnel. */
    public function serieMensual(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT strftime('%Y-%m', fecha_visita) AS mes,
                    COUNT(*) AS visitantes,
                    SUM(CASE WHEN fecha_conversion IS NOT NULL THEN 1 ELSE 0 END) AS clientes
             FROM usuarios_funnel
             WHERE fecha_visita BETWEEN :desde AND :hasta
             GROUP BY mes ORDER BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }
}
