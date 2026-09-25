<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\MonedaRepository;
use App\Repositories\SegmentacionRepository;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php database/seed.php` (mismas variables) para tener datos.
 */
final class MonedaYSegmentacionTest extends IntegracionTestCase
{
    public function testSimboloDeMonedaConocida(): void
    {
        self::assertSame('$', MonedaRepository::simbolo('USD'));
    }

    public function testSimboloPorDefectoParaCodigoDesconocidoEsElMismoCodigo(): void
    {
        self::assertSame('ZZZ', MonedaRepository::simbolo('ZZZ'));
    }

    public function testMoneyMonedaCombinaSimboloMontoYCodigo(): void
    {
        $texto = money_moneda(1234.5, 'USD');
        self::assertSame('$1,234.50 USD', $texto);
    }

    public function testMoneyMonedaPoneElSignoAntesDelSimbolo(): void
    {
        self::assertSame('-$1,234.50 USD', money_moneda(-1234.5, 'USD'));
    }

    /**
     * Si al cliente le devolvimos la plata, esa plata no es valor que haya
     * dejado: el LTV tiene que restar sus notas de credito. Reproduce el
     * caso real de un cliente con todo lo cobrado devuelto, que aparecia
     * con su LTV bruto intacto.
     */
    public function testLtvPorCohorteDescuentaLasNotasDeCredito(): void
    {
        $db = Database::connection();
        $boleta = $db->query('SELECT id, cliente_id, moneda_codigo FROM boletas ORDER BY id LIMIT 1')->fetch();
        self::assertNotFalse($boleta, 'este test asume que el seed dejo al menos una boleta');

        $cohorte = $db->prepare("SELECT to_char(fecha_alta, 'YYYY-MM') FROM clientes WHERE id = :id");
        $cohorte->execute([':id' => $boleta['cliente_id']]);
        $mesCohorte = (string) $cohorte->fetchColumn();

        $repo = new SegmentacionRepository();
        $ltvDe = static function (array $filas, string $mes): ?float {
            foreach ($filas as $fila) {
                if ($fila['cohorte'] === $mes) {
                    return (float) $fila['ltv_promedio'];
                }
            }
            return null;
        };

        $antes = $ltvDe($repo->ltvPorCohorte(), $mesCohorte);
        self::assertNotNull($antes);

        $stmt = $db->prepare(
            'INSERT INTO notas_credito (boleta_id, cliente_id, monto, moneda_codigo, fecha, motivo)
             VALUES (:b, :c, 500, :m, CURRENT_DATE, :motivo) RETURNING id'
        );
        $stmt->execute([
            ':b' => $boleta['id'],
            ':c' => $boleta['cliente_id'],
            ':m' => $boleta['moneda_codigo'],
            ':motivo' => 'Nota de prueba ' . uniqid(),
        ]);

        $despues = $ltvDe($repo->ltvPorCohorte(), $mesCohorte);
        self::assertNotNull($despues);
        self::assertLessThan($antes, $despues, 'emitir una nota de credito tiene que bajar el LTV de esa cohorte');
    }

    public function testTopPorDimensionEstaOrdenadoDescendentePorFacturacion(): void
    {
        $filas = (new SegmentacionRepository())->topPorPais(5);

        $totales = array_column($filas, 'total_facturado');
        $ordenados = $totales;
        rsort($ordenados);
        self::assertSame($ordenados, $totales, 'debe venir ordenado de mayor a menor facturacion');

        foreach ($filas as $fila) {
            self::assertGreaterThanOrEqual(0.0, (float) $fila['total_facturado']);
            self::assertGreaterThan(0, (int) $fila['clientes']);
        }
    }
}
