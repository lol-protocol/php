<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\EstadoBoleta;
use App\Repositories\BoletaRepository;
use PDO;

/**
 * El filtro de estado de Cobros se resuelve en SQL (BoletaRepository::ESTADO_SQL)
 * para poder paginar sin traer todo el rango a PHP, y el estado que muestra cada
 * fila lo calcula PHP (EstadoBoleta::calcular()). Es la misma regla escrita dos
 * veces: aca se las compara con todos los bordes (saldo de 0.01, sobrepago,
 * vence ayer/hoy/manana, anulada), asi que no pueden diverger sin que falle.
 */
final class EstadoBoletaSqlTest extends IntegracionTestCase
{
    public function testElEstadoEnSqlCoincideConElDePhpEnTodosLosBordes(): void
    {
        $montos = ['100.00', '0.01', '1234.56'];
        $pagados = ['0.00', '0.01', '50.00', '99.99', '100.00', '1234.55', '1234.56', '1500.00'];
        $vencimientos = [-30, -1, 0, 1, 30];

        $valores = [];
        $esperado = [];
        $id = 0;
        foreach ($montos as $monto) {
            foreach ($pagados as $pagado) {
                foreach ($vencimientos as $dias) {
                    foreach ([false, true] as $anulada) {
                        $id++;
                        $valores[] = sprintf(
                            '(%d, %s::numeric, %s::numeric, CURRENT_DATE + %d, %s)',
                            $id,
                            $monto,
                            $pagado,
                            $dias,
                            $anulada ? 'TRUE' : 'FALSE'
                        );
                        $esperado[$id] = EstadoBoleta::calcular(
                            (float) $monto,
                            (float) $pagado,
                            date('Y-m-d', strtotime("{$dias} days")),
                            null,
                            $anulada
                        )['estado'];
                    }
                }
            }
        }

        $obtenido = Database::connection()->query(
            'SELECT b.id, ' . BoletaRepository::ESTADO_SQL . ' AS estado
             FROM (VALUES ' . implode(', ', $valores) . ') AS b(id, monto, pagado, fecha_vencimiento, anulada)
             ORDER BY b.id'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        self::assertCount(count($esperado), $obtenido, 'cada combinacion tiene que tener un estado');
        self::assertSame($esperado, array_map('strval', $obtenido), 'el estado en SQL difiere del de PHP');
        // Los cinco estados existen en la grilla: si no, la comparacion no probaria todas las ramas.
        self::assertEqualsCanonicalizing(
            ['anulada', 'pagada', 'vencida', 'parcial', 'pendiente'],
            array_values(array_unique($esperado))
        );
    }
}
