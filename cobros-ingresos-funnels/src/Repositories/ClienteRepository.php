<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Paginacion;
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

    public function porId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, p.nombre AS pais_nombre, p.moneda_codigo, m.simbolo AS moneda_simbolo
             FROM clientes c
             JOIN paises p ON p.codigo = c.pais_codigo
             JOIN monedas m ON m.codigo = p.moneda_codigo
             WHERE c.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Listado paginado de clientes con buscador opcional por nombre/email.
     *
     * @return array{filas: array, total: int, totalPaginas: int}
     */
    public function buscar(string $query = '', int $pagina = 1): array
    {
        $where = '';
        $params = [];
        if ($query !== '') {
            $where = ' WHERE c.nombre ILIKE :q OR c.email ILIKE :q';
            $params[':q'] = '%' . $query . '%';
        }

        $stmtTotal = $this->db->prepare("SELECT COUNT(*) FROM clientes c{$where}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT c.id, c.nombre, c.email, c.segmento, c.fecha_alta, p.nombre AS pais_nombre
             FROM clientes c
             JOIN paises p ON p.codigo = c.pais_codigo
             {$where}
             ORDER BY c.nombre
             LIMIT :limite OFFSET :offset"
        );
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->bindValue(':limite', Paginacion::POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', Paginacion::offset($pagina), PDO::PARAM_INT);
        $stmt->execute();

        return [
            'filas' => $stmt->fetchAll(),
            'total' => $total,
            'totalPaginas' => Paginacion::totalPaginas($total),
        ];
    }

    /** Lista plana (sin paginar) para poblar el <select> de los formularios de alta. */
    public function paraSelector(int $limite = 500): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, email FROM clientes ORDER BY nombre LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Alta manual de un cliente. Devuelve el id creado. */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais_codigo, ciudad, idioma, genero, fecha_nacimiento)
             VALUES (:nombre, :email, :segmento, :fecha_alta, :pais_codigo, :ciudad, :idioma, :genero, :fecha_nacimiento)
             RETURNING id"
        );
        $stmt->execute([
            ':nombre' => $datos['nombre'],
            ':email' => $datos['email'],
            ':segmento' => $datos['segmento'],
            ':fecha_alta' => $datos['fecha_alta'],
            ':pais_codigo' => $datos['pais_codigo'],
            ':ciudad' => $datos['ciudad'],
            ':idioma' => $datos['idioma'],
            ':genero' => $datos['genero'],
            ':fecha_nacimiento' => $datos['fecha_nacimiento'],
        ]);
        return (int) $stmt->fetchColumn();
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
