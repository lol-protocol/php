<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Busqueda;
use App\ClienteMenorDeEdad;
use App\Database;
use App\MayoriaDeEdad;
use App\Paginacion;
use App\RangoEdad;
use DateTimeImmutable;
use PDO;

/**
 * CRUD de clientes. La segmentacion/LTV vive en SegmentacionRepository.
 *
 * @phpstan-import-type Pagina from Paginacion
 */
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
     * @return Pagina
     */
    public function buscar(string $query = '', int $pagina = 1): array
    {
        [$where, $params] = Busqueda::filtro($query, ['c.nombre', 'c.email'], 'q', ' WHERE ');

        return Paginacion::consultar(
            $this->db,
            columnas: 'c.id, c.nombre, c.email, c.segmento, c.fecha_alta, p.nombre AS pais_nombre',
            desde: "clientes c JOIN paises p ON p.codigo = c.pais_codigo{$where}",
            orden: 'c.nombre, c.id',
            params: $params,
            pagina: $pagina,
            desdeDelConteo: "clientes c{$where}",
        );
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
             WHERE id IN (SELECT id FROM clientes WHERE anonimizado_en IS NULL ORDER BY nombre, id LIMIT :limite) OR id = :incluir
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

    /**
     * Alta manual de un cliente. Devuelve el id creado.
     *
     * $datos['fecha_nacimiento'] (Y-m-d) NO se guarda (migracion 021): sirve para dos cosas y se descarta. Primero se comprueba
     * que la persona cumplio la edad de mayoria de su pais (ClienteMenorDeEdad si no: la base ya no puede, solo ve el tramo);
     * despues se guarda el tramo de edad de hoy en clientes.rango_de_edad.
     *
     * @throws ClienteMenorDeEdad si no cumplio la edad de mayoria del pais
     */
    public function crear(array $datos): int
    {
        $hoy = new DateTimeImmutable('today');
        $pais = (new PaisRepository())->mayoriaDeEdad((string) $datos['pais_codigo']);
        // Un pais que no existe lo rechaza la clave foranea al insertar, igual que antes: aca no se adivina su edad.
        if ($pais !== null && !MayoriaDeEdad::cumplida((string) $datos['fecha_nacimiento'], $hoy, $pais['mayoria_de_edad'])) {
            throw new ClienteMenorDeEdad(MayoriaDeEdad::mensaje($pais['mayoria_de_edad'], $pais['nombre']));
        }

        $stmt = $this->db->prepare(
            "INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais_codigo, ciudad, idioma, genero, rango_de_edad)
             VALUES (:nombre, :email, :segmento, :fecha_alta, :pais_codigo, :ciudad, :idioma, :genero, :rango_de_edad)
             RETURNING id"
        );
        $stmt->execute([
            ':nombre' => $datos['nombre'],
            ':email' => mb_strtolower($datos['email']),   // el unico es sobre lower(email): se guarda ya en minusculas
            ':segmento' => $datos['segmento'],
            ':fecha_alta' => $datos['fecha_alta'],
            ':pais_codigo' => $datos['pais_codigo'],
            ':ciudad' => $datos['ciudad'],
            ':idioma' => $datos['idioma'],
            ':genero' => $datos['genero'],
            ':rango_de_edad' => RangoEdad::deFecha((string) $datos['fecha_nacimiento'], $hoy),
        ]);
        return (int) $stmt->fetchColumn();
    }

}
