<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Paginacion;
use Generator;
use PDO;

/**
 * La lista de cobranza: quien debe, cuanto y desde cuando. Es la cartera pendiente
 * (la misma que suma el Dashboard) vista por cliente en vez de por tramo de
 * antiguedad. Sale de IngresosYCobrosRepository::saldosAbiertosSql(), la unica
 * definicion de cartera, asi que el total de esta lista y el del Dashboard no pueden
 * discrepar. Todo en USD a la tasa de hoy, como la cartera: es plata por cobrar.
 *
 * Un saldo vencido es el de una boleta cuyo vencimiento ya paso (vence hoy = al dia,
 * la misma regla que EstadoBoleta).
 *
 * @phpstan-import-type Pagina from Paginacion
 */
final class CobranzaRepository
{
    /** Que clientes se listan; el primero es el que se ve si no se pide otro. */
    public const ALCANCES = ['vencidos' => 'Con saldo vencido', 'todos' => 'Todo el saldo abierto'];

    /** Como se ordenan; el primero es el que se ve si no se pide otro. */
    public const ORDENES = [
        'vencido' => 'Mayor monto vencido',
        'antiguedad' => 'Más días de vencimiento',
        'saldo' => 'Mayor saldo total',
        'cliente' => 'Nombre del cliente',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** El valor pedido por URL si es uno de $permitidos, y '' si no es ninguno (la pantalla avisa y usa el primero). */
    public static function opcionPedida(string $pedido, array $permitidos): string
    {
        return array_key_exists($pedido, $permitidos) ? $pedido : '';
    }

    /**
     * Una pagina de clientes con saldo abierto. Cada fila: el cliente (con su email
     * y su pais), cuantas boletas debe, su saldo y su saldo vencido en USD, y los dias
     * de la boleta que lleva mas tiempo vencida (cero o negativo = ninguna vencida).
     * Cualquier alcance u orden desconocido cuenta como el primero de su lista.
     *
     * @return Pagina
     */
    public function listado(string $alcance, string $orden, int $pagina = 1): array
    {
        $consulta = self::consultaDelListado($alcance, $orden);

        return Paginacion::consultar(
            $this->db,
            columnas: $consulta['columnas'],
            desde: $consulta['desde'],
            orden: $consulta['orden'],
            params: [],
            pagina: $pagina,
        );
    }

    /**
     * TODOS los clientes que listado() mostraria con ese alcance y orden (todas las
     * paginas), de a uno y en el mismo orden, para exportarlos: ver Paginacion::recorrer().
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function exportar(string $alcance, string $orden): Generator
    {
        $consulta = self::consultaDelListado($alcance, $orden);

        return Paginacion::recorrer($this->db, $consulta['columnas'], $consulta['desde'], $consulta['orden'], []);
    }

    /**
     * La consulta del listado de Cobranza, armada una sola vez para que la pagina que se
     * ve y lo que se exporta no puedan discrepar en alcance ni en orden.
     *
     * @return array{columnas: string, desde: string, orden: string}
     */
    private static function consultaDelListado(string $alcance, string $orden): array
    {
        $filtro = $alcance === 'todos' ? '' : ' WHERE t.vencido_usd > 0';
        // Orden total, con desempate por cliente: si no, dos clientes empatados pueden
        // cambiar de lugar entre una pagina y la siguiente. Nunca viene de la URL: sale
        // de este match.
        $ordenSql = match ($orden) {
            'antiguedad' => 't.dias_mayor_vencimiento DESC, t.vencido_usd DESC, t.cliente_id',
            'saldo' => 't.saldo_usd DESC, t.cliente_id',
            'cliente' => 't.nombre ASC, t.cliente_id',
            default => 't.vencido_usd DESC, t.dias_mayor_vencimiento DESC, t.cliente_id',
        };

        return [
            'columnas' => 't.*',
            'desde' => '(' . self::porClienteSql() . ") t{$filtro}",
            'orden' => $ordenSql,
        ];
    }

    /**
     * Los totales de toda la cartera, sin importar el filtro de la pantalla: cuantos
     * clientes deben, cuanto es el saldo abierto (el mismo numero que el Dashboard
     * llama «Cartera pendiente») y cuanto de eso ya esta vencido.
     *
     * @return array{clientes: int, saldo_usd: float, vencido_usd: float}
     */
    public function resumen(): array
    {
        $fila = $this->db->query(
            'SELECT COUNT(DISTINCT s.cliente_id) AS clientes,
                    COALESCE(SUM(s.saldo_usd), 0) AS saldo_usd,
                    COALESCE(SUM(s.saldo_usd) FILTER (WHERE s.dias_vencido > 0), 0) AS vencido_usd
             FROM (' . IngresosYCobrosRepository::saldosAbiertosSql() . ') s'
        )->fetch();

        return [
            'clientes' => (int) $fila['clientes'],
            'saldo_usd' => (float) $fila['saldo_usd'],
            'vencido_usd' => (float) $fila['vencido_usd'],
        ];
    }

    private static function porClienteSql(): string
    {
        return 'SELECT c.id AS cliente_id, c.nombre, c.email, c.ciudad, p.nombre AS pais,
                       COUNT(*) AS boletas,
                       SUM(s.saldo_usd) AS saldo_usd,
                       COALESCE(SUM(s.saldo_usd) FILTER (WHERE s.dias_vencido > 0), 0) AS vencido_usd,
                       MAX(s.dias_vencido) AS dias_mayor_vencimiento
                FROM (' . IngresosYCobrosRepository::saldosAbiertosSql() . ') s
                JOIN clientes c ON c.id = s.cliente_id
                JOIN paises p ON p.codigo = c.pais_codigo
                GROUP BY c.id, c.nombre, c.email, c.ciudad, p.nombre';
    }
}
