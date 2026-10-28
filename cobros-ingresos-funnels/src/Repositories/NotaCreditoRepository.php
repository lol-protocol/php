<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Paginacion;
use PDO;

/**
 * Notas de credito (devoluciones). Se emiten al anular una boleta que ya
 * tenia pagos: los pagos quedan intactos (la plata entro de verdad) y la
 * nota registra lo que hay que devolver. Los reportes de cobros restan
 * estas notas para mostrar el neto, el dia en que se emitieron. Cuando la
 * plata se le devuelve al cliente de verdad, la nota guarda ese dia
 * (fecha_devolucion, migracion 012): mientras sea NULL, la devolucion esta
 * pendiente.
 *
 * @phpstan-import-type Pagina from Paginacion
 */
final class NotaCreditoRepository
{
    /** Los filtros de la pantalla de notas; el primero es el que se ve si no se pide otro. */
    public const ESTADOS = ['pendientes' => 'Pendientes de devolver', 'devueltas' => 'Ya devueltas', 'todas' => 'Todas'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Emite una nota de credito. Devuelve el id creado.
     *
     * Su tasa de cambio es el promedio, ponderado por monto, de las tasas de los
     * pagos vigentes de la boleta: la nota devuelve esos pagos, y asi cobro y
     * devolucion se cancelan en USD aunque la tasa se haya movido desde que se
     * cobro. Con la tasa de hoy, un pago de 100 a 0.80 devuelto a 0.85 dejaria un
     * "cobrado" negativo de 5 dolares que nadie cobro ni devolvio. Una boleta sin
     * pagos vigentes no da promedio (NULL) y la base graba la tasa del dia
     * (migracion 011).
     */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notas_credito (boleta_id, cliente_id, monto, moneda_codigo, fecha, motivo, tasa_a_usd)
             VALUES (:boleta_id, :cliente_id, :monto, :moneda_codigo, :fecha, :motivo,
                     (SELECT round(SUM(p.monto * p.tasa_a_usd) / SUM(p.monto), 12)
                      FROM pagos p
                      WHERE p.boleta_id = :boleta_id_pagos AND p.moneda_codigo = :moneda_pagos AND NOT p.anulada))
             RETURNING id'
        );
        $stmt->execute([
            ':boleta_id' => $datos['boleta_id'],
            ':cliente_id' => $datos['cliente_id'],
            ':monto' => $datos['monto'],
            ':moneda_codigo' => $datos['moneda_codigo'],
            ':fecha' => $datos['fecha'],
            ':motivo' => $datos['motivo'],
            ':boleta_id_pagos' => $datos['boleta_id'],
            ':moneda_pagos' => $datos['moneda_codigo'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    /** Todas las notas de credito de un cliente (para su ficha), mas recientes primero. */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, boleta_id, monto, moneda_codigo, fecha, motivo, fecha_devolucion
             FROM notas_credito WHERE cliente_id = :id
             ORDER BY fecha DESC, id DESC'
        );
        $stmt->execute([':id' => $clienteId]);
        return $stmt->fetchAll();
    }

    /** El filtro pedido por URL si es uno de los que existen, y '' si no es ninguno (la pantalla avisa y muestra las pendientes). */
    public static function estadoPedido(string $estado): string
    {
        return array_key_exists($estado, self::ESTADOS) ? $estado : '';
    }

    /**
     * Una pagina de notas segun su estado: 'pendientes' (la devolucion todavia no
     * se hizo, y se ven primero las mas viejas, que son las que llevan mas tiempo
     * esperando), 'devueltas' o 'todas' (las mas recientes primero). Cualquier otro
     * valor cuenta como 'pendientes'.
     *
     * @return Pagina
     */
    public function listado(string $estado, int $pagina = 1): array
    {
        $filtro = match ($estado) {
            'devueltas' => ' WHERE n.fecha_devolucion IS NOT NULL',
            'todas' => '',
            default => ' WHERE n.fecha_devolucion IS NULL',
        };
        $pendientes = $estado !== 'devueltas' && $estado !== 'todas';

        return Paginacion::consultar(
            $this->db,
            columnas: 'n.id, n.boleta_id, n.cliente_id, c.nombre AS cliente, n.monto, n.moneda_codigo,
                    n.fecha, n.motivo, n.fecha_devolucion',
            desde: "notas_credito n JOIN clientes c ON c.id = n.cliente_id{$filtro}",
            orden: $pendientes ? 'n.fecha ASC, n.id ASC' : 'n.fecha DESC, n.id DESC',
            params: [],
            pagina: $pagina,
            desdeDelConteo: "notas_credito n{$filtro}",
        );
    }

    public function porId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT n.id, n.boleta_id, n.cliente_id, c.nombre AS cliente, n.monto, n.moneda_codigo,
                    n.fecha, n.motivo, n.fecha_devolucion
             FROM notas_credito n JOIN clientes c ON c.id = n.cliente_id
             WHERE n.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Anota que la plata de la nota ya se devolvio ese dia, solo si seguia
     * pendiente, y devuelve si fue ESTA llamada la que lo anoto. El chequeo va en
     * la misma sentencia del UPDATE por la razon de Anulable::anularSiEstabaActiva():
     * con una lectura antes, dos envios simultaneos pasan los dos y la devolucion se
     * registra (y se audita) dos veces; asi el segundo se bloquea en la fila,
     * reevalua, no encuentra nada que cambiar y se entera de que ya estaba hecha.
     */
    public function registrarDevolucionSiEstabaPendiente(int $id, string $fecha): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notas_credito SET fecha_devolucion = :fecha
             WHERE id = :id AND fecha_devolucion IS NULL
             RETURNING id'
        );
        $stmt->execute([':id' => $id, ':fecha' => $fecha]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Cuanta plata falta devolverle a los clientes: la cantidad de notas
     * pendientes y su total en USD. Se valua a la tasa de hoy, igual que la cartera
     * pendiente: es plata por pagar hoy en moneda extranjera, no un dato historico.
     * Por eso no usa la tasa grabada en la nota (esa sirve para anular el cobro
     * original en los reportes, ver crear()).
     *
     * @return array{cantidad: int, total_usd: float}
     */
    public function pendientes(): array
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS cantidad, COALESCE(SUM(n.monto * m.tasa_a_usd), 0) AS total_usd
             FROM notas_credito n JOIN monedas m ON m.codigo = n.moneda_codigo
             WHERE n.fecha_devolucion IS NULL'
        )->fetch();

        return ['cantidad' => (int) $fila['cantidad'], 'total_usd' => (float) $fila['total_usd']];
    }

    /** Total devuelto en el rango, en USD a la tasa de cada nota (para netear los cobros del periodo). */
    public function totalEnRangoUsd(string $desde, string $hasta): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(n.monto * n.tasa_a_usd), 0)
             FROM notas_credito n
             WHERE n.fecha BETWEEN :desde AND :hasta'
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return (float) $stmt->fetchColumn();
    }

    /** Total devuelto por mes en el rango, en USD a la tasa de cada nota. @return array<string, float> mes => total */
    public function porMesUsd(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(n.fecha, 'YYYY-MM') AS mes, SUM(n.monto * n.tasa_a_usd) AS total
             FROM notas_credito n
             WHERE n.fecha BETWEEN :desde AND :hasta
             GROUP BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);

        $porMes = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porMes[$fila['mes']] = (float) $fila['total'];
        }
        return $porMes;
    }
}
