<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\EstadoBoleta;
use App\Paginacion;
use PDO;

/** CRUD de boletas. El reporting de ingresos vive en IngresosRepository. */
final class BoletaRepository
{
    use Anulable;

    /**
     * La regla de EstadoBoleta::calcular() escrita en SQL, para filtrar y
     * paginar por estado en la base (ver listado()). Es la misma regla dos
     * veces, asi que EstadoBoletaSqlTest las compara con todos los bordes:
     * quien cambie una tiene que cambiar la otra o ese test falla. Se aplica
     * sobre boletas_con_saldo, que es de donde sale b.pagado.
     */
    public const ESTADO_SQL = "CASE WHEN b.anulada THEN 'anulada'
                                    WHEN round(b.monto - b.pagado, 2) <= 0.01 THEN 'pagada'
                                    WHEN b.fecha_vencimiento < CURRENT_DATE THEN 'vencida'
                                    WHEN b.pagado > 0 THEN 'parcial'
                                    ELSE 'pendiente' END";

    private PDO $db;
    private string $tablaAnulable = 'boletas';

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Alta manual de una boleta. Devuelve el id creado. */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO boletas (cliente_id, concepto, monto, moneda_codigo, fecha_emision, fecha_vencimiento)
             VALUES (:cliente_id, :concepto, :monto, :moneda_codigo, :fecha_emision, :fecha_vencimiento)
             RETURNING id'
        );
        $stmt->execute([
            ':cliente_id' => $datos['cliente_id'],
            ':concepto' => $datos['concepto'],
            ':monto' => $datos['monto'],
            ':moneda_codigo' => $datos['moneda_codigo'],
            ':fecha_emision' => $datos['fecha_emision'],
            ':fecha_vencimiento' => $datos['fecha_vencimiento'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Toma el candado de la fila hasta el fin de la transaccion en curso.
     * Serializa las operaciones que validan contra el saldo de una boleta y
     * despues escriben (pagar, editar, anular): sin esto dos pagos
     * simultaneos pasaban la validacion de saldo y sobrecobraban, y un pago
     * cargado mientras se anulaba la boleta quedaba fuera de la nota de credito.
     */
    public function bloquear(int $id): void
    {
        $this->db->prepare('SELECT id FROM boletas WHERE id = :id FOR UPDATE')->execute([':id' => $id]);
    }

    /**
     * Incluye pagado/saldo (igual que listado()/porCliente()) para poder
     * validar sobrepagos, y primer_pago para no dejar mover la emision por
     * delante de los pagos que la boleta ya tiene. Los tres salen de la vista
     * boletas_con_saldo, la unica definicion de "cuanto se pago" (ver la
     * migracion 003).
     */
    public function porId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, c.nombre AS cliente
             FROM boletas_con_saldo b JOIN clientes c ON c.id = b.cliente_id
             WHERE b.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['saldo'] = round((float) $row['saldo'], 2);
        return $row;
    }

    /** Edita concepto/monto/fechas. No se puede reasignar el cliente ni la moneda. */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            'UPDATE boletas SET concepto = :concepto, monto = :monto,
                fecha_emision = :fecha_emision, fecha_vencimiento = :fecha_vencimiento
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':concepto' => $datos['concepto'],
            ':monto' => $datos['monto'],
            ':fecha_emision' => $datos['fecha_emision'],
            ':fecha_vencimiento' => $datos['fecha_vencimiento'],
        ]);
    }

    /**
     * Todo el historial de boletas de un cliente puntual (para su ficha), sin
     * filtro de fecha. Incluye las anuladas (quedan marcadas, no se ocultan).
     * Las mas recientes primero; a igual fecha, la de mayor id (la cargada
     * despues): sin ese desempate el SQL no promete ningun orden entre ellas.
     */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.id, b.concepto, b.monto, b.moneda_codigo, b.fecha_emision, b.fecha_vencimiento, b.anulada, b.pagado
             FROM boletas_con_saldo b
             WHERE b.cliente_id = :id
             ORDER BY b.fecha_emision DESC, b.id DESC"
        );
        $stmt->execute([':id' => $clienteId]);

        return self::conEstadoCalculado($stmt->fetchAll());
    }

    /**
     * Listado paginado para la pantalla de Cobros. Incluye anuladas (con su
     * badge) salvo que se filtre explicitamente por otro estado.
     *
     * El estado (pagada/parcial/pendiente/vencida/anulada) no se guarda en la
     * base: el que muestra cada fila lo calcula EstadoBoleta a partir de los
     * pagos aplicados. Para filtrar por estado y paginar sin traer todo el rango
     * a PHP, la misma regla esta escrita en SQL (ESTADO_SQL). Antes ese camino
     * leia todas las boletas del rango, las filtraba en un array y recien ahi
     * cortaba la pagina: con 120 mil boletas el limite de memoria de PHP no
     * alcanzaba y la pantalla salia en blanco con un 500 (lo vigila
     * RendimientoAEscalaTest).
     *
     * @return array{filas: array, total: int, totalPaginas: int, pagina: int}
     */
    public function listado(string $desde, string $hasta, ?string $estado = null, ?string $cliente = null, int $pagina = 1): array
    {
        $params = [':desde' => $desde, ':hasta' => $hasta];
        $filtroCliente = '';
        if ($cliente !== null && $cliente !== '') {
            $filtroCliente = ' AND c.nombre ILIKE :cliente';
            $params[':cliente'] = '%' . $cliente . '%';
        }

        // Sin filtro de estado el conteo no necesita los pagos (ni la vista que los suma por boleta).
        $filtroEstado = '';
        $tablaDelConteo = 'boletas';
        if ($estado !== null && $estado !== '') {
            $filtroEstado = ' AND ' . self::ESTADO_SQL . ' = :estado';
            $tablaDelConteo = 'boletas_con_saldo';
            $params[':estado'] = $estado;
        }

        $stmtTotal = $this->db->prepare(
            "SELECT COUNT(*) FROM {$tablaDelConteo} b JOIN clientes c ON c.id = b.cliente_id
             WHERE b.fecha_emision BETWEEN :desde AND :hasta{$filtroCliente}{$filtroEstado}"
        );
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();
        $pagina = Paginacion::acotar($pagina, $total);

        // El desempate por id da un orden total: sin el, dos boletas del mismo dia
        // podian cambiar de lugar entre la pagina 1 y la 2.
        $stmt = $this->db->prepare(
            "SELECT b.id, b.concepto, b.monto, b.moneda_codigo, b.fecha_emision, b.fecha_vencimiento, b.anulada,
                    c.id AS cliente_id, c.nombre AS cliente, b.pagado
             FROM boletas_con_saldo b
             JOIN clientes c ON c.id = b.cliente_id
             WHERE b.fecha_emision BETWEEN :desde AND :hasta{$filtroCliente}{$filtroEstado}
             ORDER BY b.fecha_emision DESC, b.id DESC
             LIMIT :limite OFFSET :offset"
        );
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->bindValue(':limite', Paginacion::POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', Paginacion::offset($pagina), PDO::PARAM_INT);
        $stmt->execute();

        return [
            'filas' => self::conEstadoCalculado($stmt->fetchAll()),
            'total' => $total,
            'totalPaginas' => Paginacion::totalPaginas($total),
            'pagina' => $pagina,
        ];
    }

    /** Agrega saldo/estado calculado (EstadoBoleta) a cada fila de un fetchAll() de boletas. */
    private static function conEstadoCalculado(array $filas): array
    {
        foreach ($filas as &$fila) {
            $calculo = EstadoBoleta::calcular(
                (float) $fila['monto'],
                (float) $fila['pagado'],
                $fila['fecha_vencimiento'],
                null,
                (bool) $fila['anulada']
            );
            $fila['saldo'] = $calculo['saldo'];
            $fila['estado'] = $calculo['estado'];
        }

        return $filas;
    }
}
