<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\MayoriaDeEdad;
use PDO;

final class PaisRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Para poblar selects: codigo, nombre y la moneda que le corresponde. */
    public function listado(): array
    {
        return $this->db->query(
            'SELECT p.codigo, p.nombre, p.moneda_codigo
             FROM paises p
             ORDER BY p.nombre'
        )->fetchAll();
    }

    /** true si $codigo es el codigo de un pais del catalogo (el formulario solo ofrece esos). */
    public function existe(string $codigo): bool
    {
        $stmt = $this->db->prepare('SELECT EXISTS (SELECT 1 FROM paises WHERE codigo = :codigo)');
        $stmt->execute([':codigo' => $codigo]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * El nombre y la edad de mayoria del pais $codigo (migracion 010), o null si
     * no esta en el catalogo: sirve tambien para saber si existe.
     *
     * @return array{nombre: string, mayoria_de_edad: int}|null
     */
    public function mayoriaDeEdad(string $codigo): ?array
    {
        $stmt = $this->db->prepare('SELECT nombre, mayoria_de_edad FROM paises WHERE codigo = :codigo');
        $stmt->execute([':codigo' => $codigo]);
        $fila = $stmt->fetch();
        if ($fila === false) {
            return null;
        }

        return ['nombre' => (string) $fila['nombre'], 'mayoria_de_edad' => (int) $fila['mayoria_de_edad']];
    }

    /** La edad de mayoria mas baja de todos los paises: el tope del selector de fecha, que no sabe todavia el pais. */
    public function menorMayoriaDeEdad(): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MIN(mayoria_de_edad), :general) FROM paises');
        $stmt->execute([':general' => MayoriaDeEdad::POR_DEFECTO]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Los paises cuya edad no es la general, para avisarlo en el formulario de alta,
     * agrupados por edad (de menor a mayor) con los nombres en orden alfabetico.
     *
     * @return array<int, list<string>> edad => nombres de paises
     */
    public function conMayoriaDeEdadDistinta(): array
    {
        $stmt = $this->db->prepare(
            'SELECT mayoria_de_edad, nombre FROM paises WHERE mayoria_de_edad <> :general ORDER BY mayoria_de_edad, nombre'
        );
        $stmt->execute([':general' => MayoriaDeEdad::POR_DEFECTO]);
        $filas = $stmt->fetchAll();

        $porEdad = [];
        foreach ($filas as $fila) {
            $porEdad[(int) $fila['mayoria_de_edad']][] = (string) $fila['nombre'];
        }

        return $porEdad;
    }
}
