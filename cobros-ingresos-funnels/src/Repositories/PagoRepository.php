<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Busqueda;
use App\Database;
use App\Paginacion;
use Generator;
use PDO;

/**
 * CRUD de pagos. El reporting de cobros vive en IngresosYCobrosRepository.
 *
 * @phpstan-import-type Pagina from Paginacion
 */
final class PagoRepository
{
    use Anulable;

    private PDO $db;
    private string $tablaAnulable = 'pagos';

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Alta manual de un pago. Devuelve el id creado. */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pagos (boleta_id, cliente_id, monto, moneda_codigo, fecha_pago, metodo)
             VALUES (:boleta_id, :cliente_id, :monto, :moneda_codigo, :fecha_pago, :metodo)
             RETURNING id'
        );
        $stmt->execute([
            ':boleta_id' => $datos['boleta_id'] ?: null,
            ':cliente_id' => $datos['cliente_id'],
            ':monto' => $datos['monto'],
            ':moneda_codigo' => $datos['moneda_codigo'],
            ':fecha_pago' => $datos['fecha_pago'],
            ':metodo' => $datos['metodo'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Toma el candado de la fila hasta el fin de la transaccion en curso.
     * Serializa dos operaciones sobre el mismo pago (editar, anular): cada una
     * lo relee ya con el candado, asi la segunda ve lo que hizo la primera en
     * vez de decidir con una lectura vieja. Siempre se toma antes que el de su
     * boleta (BoletaRepository::bloquear()): el mismo orden en todos los flujos
     * evita que dos operaciones se esperen entre si.
     */
    public function bloquear(int $id): void
    {
        $this->db->prepare('SELECT id FROM pagos WHERE id = :id FOR UPDATE')->execute([':id' => $id]);
    }

    public function porId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.nombre AS cliente
             FROM pagos p JOIN clientes c ON c.id = p.cliente_id
             WHERE p.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Edita monto/fecha/metodo. No se puede reasignar el cliente, la boleta ni la moneda. */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            'UPDATE pagos SET monto = :monto, fecha_pago = :fecha_pago, metodo = :metodo WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':monto' => $datos['monto'],
            ':fecha_pago' => $datos['fecha_pago'],
            ':metodo' => $datos['metodo'],
        ]);
    }

    /**
     * Listado paginado para la pantalla de Pagos. Incluye los anulados
     * (marcados) para no perder el rastro de la correccion.
     *
     * @return Pagina
     */
    public function listado(string $desde, string $hasta, ?string $cliente = null, int $pagina = 1): array
    {
        $consulta = $this->consultaDelListado($desde, $hasta, $cliente);

        return Paginacion::consultar(
            $this->db,
            columnas: $consulta['columnas'],
            desde: $consulta['desde'],
            orden: $consulta['orden'],
            params: $consulta['params'],
            pagina: $pagina,
        );
    }

    /**
     * TODOS los pagos que listado() mostraria con esos filtros (todas las paginas), de a
     * uno y en el mismo orden, para exportarlos: ver Paginacion::recorrer().
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function exportar(string $desde, string $hasta, ?string $cliente = null): Generator
    {
        $consulta = $this->consultaDelListado($desde, $hasta, $cliente);

        return Paginacion::recorrer($this->db, $consulta['columnas'], $consulta['desde'], $consulta['orden'], $consulta['params']);
    }

    /**
     * La consulta del listado de Pagos, armada una sola vez para que la pagina que se ve y
     * lo que se exporta no puedan discrepar en filtros ni en orden.
     *
     * @return array{columnas: string, desde: string, orden: string, params: array<string, string>}
     */
    private function consultaDelListado(string $desde, string $hasta, ?string $cliente): array
    {
        $params = [':desde' => $desde, ':hasta' => $hasta];
        $filtroCliente = '';
        if ($cliente !== null && $cliente !== '') {
            $filtroCliente = ' AND c.nombre ILIKE :cliente';
            $params[':cliente'] = Busqueda::contiene($cliente);
        }

        return [
            'columnas' => 'p.id, p.monto, p.moneda_codigo, p.fecha_pago, p.metodo, p.boleta_id, p.anulada,
                    c.id AS cliente_id, c.nombre AS cliente',
            'desde' => "pagos p JOIN clientes c ON c.id = p.cliente_id
             WHERE p.fecha_pago BETWEEN :desde AND :hasta{$filtroCliente}",
            'orden' => 'p.fecha_pago DESC, p.id DESC',
            'params' => $params,
        ];
    }

    /**
     * Todo el historial de pagos de un cliente puntual (para su ficha), sin
     * filtro de fecha. Los mas recientes primero; a igual fecha, el de mayor
     * id (el cargado despues): sin ese desempate el SQL no promete ningun orden.
     */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, monto, moneda_codigo, fecha_pago, metodo, boleta_id, anulada
             FROM pagos WHERE cliente_id = :id ORDER BY fecha_pago DESC, id DESC'
        );
        $stmt->execute([':id' => $clienteId]);
        return $stmt->fetchAll();
    }
}
