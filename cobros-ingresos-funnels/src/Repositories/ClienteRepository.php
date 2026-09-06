<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Paginacion;
use PDO;

/** CRUD de clientes. La segmentacion/LTV vive en SegmentacionRepository. */
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

}
