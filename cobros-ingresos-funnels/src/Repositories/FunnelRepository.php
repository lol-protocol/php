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

    public function porPais(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.nombre AS etiqueta,
                    COUNT(*) AS visitantes,
                    SUM(CASE WHEN uf.fecha_registro IS NOT NULL THEN 1 ELSE 0 END) AS registrados,
                    SUM(CASE WHEN uf.fecha_lead IS NOT NULL THEN 1 ELSE 0 END) AS leads,
                    SUM(CASE WHEN uf.fecha_conversion IS NOT NULL THEN 1 ELSE 0 END) AS clientes
             FROM usuarios_funnel uf
             JOIN paises p ON p.codigo = uf.pais_codigo
             WHERE uf.fecha_visita BETWEEN :desde AND :hasta
             GROUP BY p.nombre ORDER BY visitantes DESC
             LIMIT 10"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    public function porGenero(string $desde, string $hasta): array
    {
        return $this->porDimension('genero', $desde, $hasta);
    }

    public function porRangoEdad(string $desde, string $hasta): array
    {
        return $this->porDimension(
            "CASE
                WHEN (CURRENT_DATE - fecha_nacimiento) / 365.25 < 25 THEN '18-24'
                WHEN (CURRENT_DATE - fecha_nacimiento) / 365.25 < 35 THEN '25-34'
                WHEN (CURRENT_DATE - fecha_nacimiento) / 365.25 < 45 THEN '35-44'
                WHEN (CURRENT_DATE - fecha_nacimiento) / 365.25 < 55 THEN '45-54'
                WHEN (CURRENT_DATE - fecha_nacimiento) / 365.25 < 65 THEN '55-64'
                ELSE '65+'
             END",
            $desde,
            $hasta
        );
    }

    /** $expresionSql siempre es fija, definida en este archivo, nunca entrada de usuario. */
    private function porDimension(string $expresionSql, string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT {$expresionSql} AS etiqueta,
                    COUNT(*) AS visitantes,
                    SUM(CASE WHEN fecha_registro IS NOT NULL THEN 1 ELSE 0 END) AS registrados,
                    SUM(CASE WHEN fecha_lead IS NOT NULL THEN 1 ELSE 0 END) AS leads,
                    SUM(CASE WHEN fecha_conversion IS NOT NULL THEN 1 ELSE 0 END) AS clientes
             FROM usuarios_funnel
             WHERE fecha_visita BETWEEN :desde AND :hasta
             GROUP BY etiqueta ORDER BY visitantes DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    public function tiempoPromedioConversionDias(): float
    {
        $valor = $this->db->query(
            'SELECT AVG(fecha_conversion - fecha_visita)
             FROM usuarios_funnel WHERE fecha_conversion IS NOT NULL'
        )->fetchColumn();

        return $valor !== null ? round((float) $valor, 1) : 0.0;
    }

    /** Visitantes y conversiones por mes, para la tendencia del funnel. */
    public function serieMensual(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(fecha_visita, 'YYYY-MM') AS mes,
                    COUNT(*) AS visitantes,
                    SUM(CASE WHEN fecha_conversion IS NOT NULL THEN 1 ELSE 0 END) AS clientes
             FROM usuarios_funnel
             WHERE fecha_visita BETWEEN :desde AND :hasta
             GROUP BY mes ORDER BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    /**
     * Cohortes por mes de primera visita: cuantos de cada cohorte convirtieron
     * a cliente dentro de 0, 1, 2 o 3 meses desde su fecha_visita (acumulado).
     */
    public function cohortes(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "WITH datos AS (
                SELECT to_char(fecha_visita, 'YYYY-MM') AS cohorte,
                       CASE WHEN fecha_conversion IS NULL THEN NULL
                            ELSE (EXTRACT(YEAR FROM fecha_conversion)::int * 12 + EXTRACT(MONTH FROM fecha_conversion)::int)
                               - (EXTRACT(YEAR FROM fecha_visita)::int * 12 + EXTRACT(MONTH FROM fecha_visita)::int)
                       END AS offset_meses
                FROM usuarios_funnel
                WHERE fecha_visita BETWEEN :desde AND :hasta
             )
             SELECT cohorte,
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE offset_meses IS NOT NULL AND offset_meses <= 0) AS m0,
                    COUNT(*) FILTER (WHERE offset_meses IS NOT NULL AND offset_meses <= 1) AS m1,
                    COUNT(*) FILTER (WHERE offset_meses IS NOT NULL AND offset_meses <= 2) AS m2,
                    COUNT(*) FILTER (WHERE offset_meses IS NOT NULL AND offset_meses <= 3) AS m3
             FROM datos
             GROUP BY cohorte
             ORDER BY cohorte"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    /** El recorrido de funnel de un cliente puntual, si entro por ahi (null si es un cliente legacy). */
    public function viajeDeCliente(int $clienteId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios_funnel WHERE cliente_id = :id');
        $stmt->execute([':id' => $clienteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
