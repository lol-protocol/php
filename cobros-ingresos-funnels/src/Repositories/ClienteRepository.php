<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Paginacion;
use PDO;

/** CRUD de clientes. La segmentacion/LTV vive en SegmentacionRepository. */
final class ClienteRepository
{
    /** Cuantos clientes caben en los desplegables de los formularios de alta (ver paraSelector()). */
    public const LIMITE_SELECTOR = 500;

    /** Valores que ofrece el formulario de alta. Los valida ClientesController y los restringe la migracion 004 (ValoresCerradosTest compara las listas). */
    public const GENEROS = ['Femenino', 'Masculino', 'No especifica'];
    public const SEGMENTOS = ['general', 'starter', 'pro', 'enterprise'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
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
     * @return array{filas: array, total: int, totalPaginas: int, pagina: int}
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
        $pagina = Paginacion::acotar($pagina, $total);

        $stmt = $this->db->prepare(
            "SELECT c.id, c.nombre, c.email, c.segmento, c.fecha_alta, p.nombre AS pais_nombre
             FROM clientes c
             JOIN paises p ON p.codigo = c.pais_codigo
             {$where}
             ORDER BY c.nombre, c.id
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
            'pagina' => $pagina,
        ];
    }

    /**
     * Lista plana (sin paginar) para poblar el <select> de los formularios de
     * alta: los primeros LIMITE_SELECTOR por nombre, mas $incluir (el cliente ya
     * elegido, ej. el de ?cliente_id=) si quedo afuera, para que el formulario
     * pueda mostrarlo seleccionado. Si hay mas clientes que el limite, el resto
     * no aparece: superaElLimiteDelSelector() lo dice para avisarlo.
     */
    public function paraSelector(?int $incluir = null): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, email FROM clientes
             WHERE id IN (SELECT id FROM clientes ORDER BY nombre, id LIMIT :limite) OR id = :incluir
             ORDER BY nombre, id'
        );
        $stmt->bindValue(':limite', self::LIMITE_SELECTOR, PDO::PARAM_INT);
        $stmt->bindValue(':incluir', $incluir ?? 0, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Los idiomas que ya tienen clientes, del mas usado al menos: el formulario
     * de alta los sugiere. El idioma es un conjunto abierto (cualquier texto
     * vale), pero el dashboard agrupa por el texto exacto, y sin sugerencias
     * "Ingles", "ingles" e "Inglés" terminaban como tres filas.
     * @return list<string>
     */
    public function idiomasEnUso(): array
    {
        return array_values(array_map('strval', $this->db->query(
            'SELECT idioma FROM clientes GROUP BY idioma ORDER BY COUNT(*) DESC, idioma'
        )->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** true si hay mas clientes de los que entran en el desplegable de los formularios de alta. */
    public function superaElLimiteDelSelector(): bool
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM clientes')->fetchColumn() > self::LIMITE_SELECTOR;
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
