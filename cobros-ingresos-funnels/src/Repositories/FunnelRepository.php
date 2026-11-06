<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\RangoEdad;
use PDO;

final class FunnelRepository
{
    use ConsultasEnRango;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Las columnas de cada etapa del funnel, para una consulta sobre usuarios_funnel: cuantos llegaron
     * (visitantes) y cuantos de ellos tienen fecha en cada etapa siguiente. COUNT(columna) cuenta
     * solo las filas donde la fecha existe. Las usan el resumen y todas las tablas «por ...», asi
     * que sus numeros no pueden discrepar.
     */
    private const ETAPAS_SQL = 'COUNT(*) AS visitantes,
                    COUNT(fecha_registro) AS registrados,
                    COUNT(fecha_lead) AS leads,
                    COUNT(fecha_conversion) AS clientes';

    /** Cuantos paises nombra la tabla «Conversion por pais» antes de juntar el resto en una fila «Otros». */
    public const PAISES_EN_LA_TABLA = 10;

    /** Conteo de usuarios en cada etapa del funnel dentro del rango de fecha_visita. */
    public function resumenEtapas(string $desde, string $hasta): array
    {
        $fila = $this->filaEnRango(
            'SELECT ' . self::ETAPAS_SQL . '
             FROM usuarios_funnel
             WHERE fecha_visita BETWEEN :desde AND :hasta',
            $desde,
            $hasta
        );

        return [
            'visitantes' => (int) $fila['visitantes'],
            'registrados' => (int) $fila['registrados'],
            'leads' => (int) $fila['leads'],
            'clientes' => (int) $fila['clientes'],
        ];
    }

    public function porCanal(string $desde, string $hasta): array
    {
        return $this->porDimension('uf.canal', $desde, $hasta);
    }

    /**
     * Los PAISES_EN_LA_TABLA con mas visitantes, mas una fila «Otros (N paises)» con la suma de
     * los demas: sin ella la tabla no cuadraba con los visitantes del periodo. Un unico pais
     * sobrante se nombra en vez de esconderlo en «Otros (1 pais)». A igual cantidad de visitantes
     * va primero el de nombre menor, para que el corte no dependa del orden en que la base
     * devuelva los empates. Son pocas filas (a lo sumo el catalogo de paises), asi que el corte
     * se hace aca y no con un LIMIT.
     */
    public function porPais(string $desde, string $hasta): array
    {
        $paises = $this->porDimension('p.nombre', $desde, $hasta, 'JOIN paises p ON p.codigo = uf.pais_codigo');

        return self::conFilaDeOtros($paises, self::PAISES_EN_LA_TABLA);
    }

    /**
     * Deja las primeras $nombradas filas y suma las demas en una «Otros (N paises)». Si sobra una
     * sola, la deja como esta.
     *
     * @param array<int, array<string, mixed>> $filas ya ordenadas, con etiqueta, visitantes, registrados, leads y clientes
     * @return array<int, array<string, mixed>>
     */
    private static function conFilaDeOtros(array $filas, int $nombradas): array
    {
        if (count($filas) <= $nombradas + 1) {
            return $filas;
        }

        $resto = array_slice($filas, $nombradas);
        $otros = ['etiqueta' => sprintf('Otros (%d países)', count($resto))];
        foreach (['visitantes', 'registrados', 'leads', 'clientes'] as $columna) {
            $otros[$columna] = array_sum(array_map('intval', array_column($resto, $columna)));
        }

        return [...array_slice($filas, 0, $nombradas), $otros];
    }

    public function porGenero(string $desde, string $hasta): array
    {
        return $this->porDimension('uf.genero', $desde, $hasta);
    }

    public function porRangoEdad(string $desde, string $hasta): array
    {
        return $this->porDimension(RangoEdad::expresionSql('uf.fecha_nacimiento'), $desde, $hasta);
    }

    /**
     * Las etapas del funnel de los usuarios del periodo, agrupadas por $etiquetaSql (cada fila trae
     * etiqueta, visitantes, registrados, leads y clientes), de mas a menos visitantes y, a igual
     * cantidad, por etiqueta. usuarios_funnel es `uf`; $uniones agrega los JOIN que la etiqueta
     * necesite. $etiquetaSql y $uniones son siempre fijos, definidos en este archivo, nunca entrada de usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    private function porDimension(string $etiquetaSql, string $desde, string $hasta, string $uniones = ''): array
    {
        return $this->filasEnRango(
            "SELECT {$etiquetaSql} AS etiqueta, " . self::ETAPAS_SQL . "
             FROM usuarios_funnel uf {$uniones}
             WHERE uf.fecha_visita BETWEEN :desde AND :hasta
             GROUP BY etiqueta ORDER BY visitantes DESC, etiqueta",
            $desde,
            $hasta
        );
    }

    /** Dias promedio de la primera visita a la conversion, de los usuarios cuya visita cae en el periodo. */
    public function tiempoPromedioConversionDias(string $desde, string $hasta): float
    {
        $valor = $this->valorEnRango(
            'SELECT AVG(fecha_conversion - fecha_visita)
             FROM usuarios_funnel
             WHERE fecha_conversion IS NOT NULL AND fecha_visita BETWEEN :desde AND :hasta',
            $desde,
            $hasta
        );

        return $valor !== null ? round((float) $valor, 1) : 0.0;
    }

    /** Visitantes y conversiones por mes, para la tendencia del funnel. */
    public function serieMensual(string $desde, string $hasta): array
    {
        return $this->filasEnRango(
            "SELECT to_char(fecha_visita, 'YYYY-MM') AS mes,
                    COUNT(*) AS visitantes,
                    COUNT(fecha_conversion) AS clientes
             FROM usuarios_funnel
             WHERE fecha_visita BETWEEN :desde AND :hasta
             GROUP BY mes ORDER BY mes",
            $desde,
            $hasta
        );
    }

    /**
     * Cohortes por mes de primera visita: cuantos de cada cohorte convirtieron
     * a cliente dentro de 0, 1, 2 o 3 meses desde su fecha_visita (acumulado).
     *
     * Ademas de m0..m3 cada fila trae meses_cerrados: cuantos meses hay entre su mes y el actual
     * (0 si es el mes en curso). La columna mN es definitiva solo si meses_cerrados > N (la ventana de N meses
     * despues de su mes ya termino): una cohorte reciente muestra porcentajes bajos porque su ventana sigue abierta.
     */
    public function cohortes(string $desde, string $hasta): array
    {
        return $this->filasEnRango(
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
                    (EXTRACT(YEAR FROM CURRENT_DATE)::int * 12 + EXTRACT(MONTH FROM CURRENT_DATE)::int)
                      - (substr(cohorte, 1, 4)::int * 12 + substr(cohorte, 6, 2)::int) AS meses_cerrados,
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE offset_meses IS NOT NULL AND offset_meses <= 0) AS m0,
                    COUNT(*) FILTER (WHERE offset_meses IS NOT NULL AND offset_meses <= 1) AS m1,
                    COUNT(*) FILTER (WHERE offset_meses IS NOT NULL AND offset_meses <= 2) AS m2,
                    COUNT(*) FILTER (WHERE offset_meses IS NOT NULL AND offset_meses <= 3) AS m3
             FROM datos
             GROUP BY cohorte
             ORDER BY cohorte",
            $desde,
            $hasta
        );
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
