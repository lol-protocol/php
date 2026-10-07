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

    /**
     * Las cinco segmentaciones del Dashboard (pais, ciudad, idioma, genero y
     * rango de edad), cada una con los $limite que mas facturaron en USD, de
     * mayor a menor, en UNA consulta.
     *
     * Antes eran cinco, y cada una recorria todas las boletas de todos los
     * clientes para sumar lo que facturo cada uno: con 120 mil boletas, 1,6
     * segundos solo en esto. Ahora se calcula una vez lo que facturo cada
     * cliente y las cinco agrupaciones parten de ese resultado (285 ms, con los
     * mismos numeros: cada dimension reparte la misma facturacion y los mismos
     * clientes, que es lo que comprueba MonedaYSegmentacionTest).
     *
     * Los clientes sin boletas vigentes no aparecen: no facturaron. La
     * etiqueta de cada fila es el valor tal cual esta guardado (el rango de
     * edad sale de RangoEdad); los titulos que se muestran los pone quien
     * dibuja.
     *
     * @return array<string, list<array{etiqueta: string, clientes: int|string, total_facturado: string}>>
     *         con las claves 'pais', 'ciudad', 'idioma', 'genero' y 'rango_edad', siempre las cinco
     */
    public function topPorDimensiones(int $limite = 5): array
    {
        $limite = max(0, $limite);   // se interpola: es un entero, nunca entrada de usuario
        $edad = RangoEdad::expresionSql('base.fecha_nacimiento');
        $filas = $this->db->query(
            "WITH f AS MATERIALIZED (
                 SELECT b.cliente_id, SUM(b.monto * m.tasa_a_usd) AS total
                 FROM boletas b JOIN monedas m ON m.codigo = b.moneda_codigo
                 WHERE NOT b.anulada GROUP BY b.cliente_id
             ), base AS MATERIALIZED (
                 SELECT c.pais_codigo, c.ciudad, c.idioma, c.genero, c.fecha_nacimiento, f.total
                 FROM f JOIN clientes c ON c.id = f.cliente_id
             )
             (SELECT 'pais' AS dimension, p.nombre AS etiqueta, COUNT(*) AS clientes, SUM(base.total) AS total_facturado
                FROM base JOIN paises p ON p.codigo = base.pais_codigo
                GROUP BY p.nombre ORDER BY total_facturado DESC LIMIT {$limite})
             UNION ALL (SELECT 'ciudad', base.ciudad, COUNT(*), SUM(base.total) AS t FROM base GROUP BY base.ciudad ORDER BY t DESC LIMIT {$limite})
             UNION ALL (SELECT 'idioma', base.idioma, COUNT(*), SUM(base.total) AS t FROM base GROUP BY base.idioma ORDER BY t DESC LIMIT {$limite})
             UNION ALL (SELECT 'genero', base.genero, COUNT(*), SUM(base.total) AS t FROM base GROUP BY base.genero ORDER BY t DESC LIMIT {$limite})
             UNION ALL (SELECT 'rango_edad', {$edad} AS e, COUNT(*), SUM(base.total) AS t FROM base GROUP BY e ORDER BY t DESC LIMIT {$limite})"
        )->fetchAll();

        $porDimension = ['pais' => [], 'ciudad' => [], 'idioma' => [], 'genero' => [], 'rango_edad' => []];
        foreach ($filas as $fila) {
            $dimension = $fila['dimension'];
            unset($fila['dimension']);
            $porDimension[$dimension][] = $fila;
        }

        return $porDimension;
    }

    /**
     * LTV (a la fecha) por cohorte de alta: para cada mes en que un grupo de
     * clientes se dio de alta, el promedio de cuanto pago cada uno hasta hoy,
     * neto de sus notas de credito -si le devolvimos la plata no es valor
     * que el cliente haya dejado-.
     *
     * Los pagos y las notas se suman cada uno una sola vez por cliente y se
     * unen despues: ni se multiplican filas entre los dos joins ni hay una
     * subconsulta de notas por cada cliente, como antes (454 ms con 30 mil
     * clientes contra 178 ms, con el mismo resultado).
     */
    public function ltvPorCohorte(): array
    {
        return $this->db->query(
            "SELECT cohorte, COUNT(*) AS clientes, AVG(total_cliente) AS ltv_promedio
             FROM (
                 SELECT to_char(c.fecha_alta, 'YYYY-MM') AS cohorte, c.id,
                        COALESCE(pg.total, 0) - COALESCE(nc.total, 0) AS total_cliente
                 FROM clientes c
                 LEFT JOIN (SELECT p.cliente_id, SUM(p.monto * m.tasa_a_usd) AS total
                            FROM pagos p JOIN monedas m ON m.codigo = p.moneda_codigo
                            WHERE NOT p.anulada GROUP BY p.cliente_id) pg ON pg.cliente_id = c.id
                 LEFT JOIN (SELECT n.cliente_id, SUM(n.monto * mn.tasa_a_usd) AS total
                            FROM notas_credito n JOIN monedas mn ON mn.codigo = n.moneda_codigo
                            GROUP BY n.cliente_id) nc ON nc.cliente_id = c.id
             ) sub
             GROUP BY cohorte
             ORDER BY cohorte"
        )->fetchAll();
    }
}
