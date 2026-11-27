<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\MonedaRepository;
use App\Repositories\SegmentacionRepository;
use DateTimeImmutable;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php bin/recrear_con_datos_de_ejemplo.php` (mismas variables) para tener datos.
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
        $texto = dinero_en_moneda(1234.5, 'USD');
        self::assertSame('$1,234.50 USD', $texto);
    }

    public function testMoneyMonedaPoneElSignoAntesDelSimbolo(): void
    {
        self::assertSame('-$1,234.50 USD', dinero_en_moneda(-1234.5, 'USD'));
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

    /**
     * El dashboard agrupa por clientes.rango_de_edad: un cliente en «Menor de 18» con una boleta tiene que salir en su propio tramo y
     * no como adulto de "18-24". Ya no se pueden dar de alta menores, pero una base anterior a la regla puede tenerlos, y es donde mas
     * importa verlos: se simula uno apagando el trigger de altas solo dentro de la transaccion de este test, que se deshace al terminar.
     * El seed no genera menores.
     */
    public function testLaSegmentacionPorEdadMuestraAlMenorEnSuPropioTramo(): void
    {
        $clientes = new ClienteRepository();
        $base = $clientes->porId(1);
        self::assertNotNull($base, 'este test asume que el cliente #1 existe (lo trae el seed)');

        $db = Database::connection();
        $db->exec('ALTER TABLE clientes DISABLE TRIGGER clientes_adulto_alta');
        $hoy = new DateTimeImmutable('today');
        $db->prepare(
            "INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais_codigo, ciudad, idioma, genero, rango_de_edad)
             VALUES ('Menor de prueba', :email, 'general', :alta, :pais, 'Rosario', 'Espanol', 'No especifica', 'Menor de 18')"
        )->execute([':email' => 'menor-' . uniqid() . '@example.com', ':alta' => $hoy->format('Y-m-d'), ':pais' => $base['pais_codigo']]);
        $menorId = (int) $db->lastInsertId('clientes_id_seq');
        (new BoletaRepository())->crear([
            'cliente_id' => $menorId,
            'concepto' => 'Boleta del menor de prueba',
            'monto' => 100,
            'moneda_codigo' => $base['moneda_codigo'],
            'fecha_emision' => $hoy->format('Y-m-d'),
            'fecha_vencimiento' => $hoy->format('Y-m-d'),
        ]);

        $tramos = array_column((new SegmentacionRepository())->topPorDimensiones(50)['rango_edad'], 'etiqueta');

        self::assertContains('Menor de 18', $tramos);
    }

    public function testCadaDimensionVieneOrdenadaDescendentePorFacturacionYConElTopPedido(): void
    {
        $porDimension = (new SegmentacionRepository())->topPorDimensiones(3);

        self::assertSame(['pais', 'ciudad', 'idioma', 'genero', 'rango_edad'], array_keys($porDimension));
        foreach ($porDimension as $dimension => $filas) {
            self::assertNotEmpty($filas, "{$dimension} tiene que traer filas");
            self::assertLessThanOrEqual(3, count($filas), "{$dimension}: no puede pasarse del top pedido");

            $totales = array_map('floatval', array_column($filas, 'total_facturado'));
            $ordenados = $totales;
            rsort($ordenados);
            self::assertSame($ordenados, $totales, "{$dimension}: debe venir ordenado de mayor a menor facturacion");

            foreach ($filas as $fila) {
                self::assertGreaterThanOrEqual(0.0, (float) $fila['total_facturado']);
                self::assertGreaterThan(0, (int) $fila['clientes']);
            }
        }
    }

    /**
     * Las cinco segmentaciones salen de una sola consulta que calcula una vez lo
     * que facturo cada cliente; sin tope, cada dimension tiene que repartir
     * exactamente la misma facturacion (la de las boletas vigentes) y contar a
     * los mismos clientes (los que tienen al menos una). Es la prueba de que
     * ninguna agrupacion pierde ni duplica filas, con una cuenta hecha aparte.
     */
    public function testCadaDimensionRepartePorCompletoLaFacturacionYLosClientes(): void
    {
        $esperado = Database::connection()->query(
            'SELECT COALESCE(SUM(b.monto * b.tasa_a_usd), 0) AS total, COUNT(DISTINCT b.cliente_id) AS clientes
             FROM boletas b
             WHERE NOT b.anulada'
        )->fetch();
        self::assertNotFalse($esperado);

        foreach ((new SegmentacionRepository())->topPorDimensiones(1000000) as $dimension => $filas) {
            self::assertEqualsWithDelta(
                (float) $esperado['total'],
                array_sum(array_map('floatval', array_column($filas, 'total_facturado'))),
                0.05,
                "{$dimension}: la facturacion repartida no suma la total"
            );
            self::assertSame(
                (int) $esperado['clientes'],
                array_sum(array_map('intval', array_column($filas, 'clientes'))),
                "{$dimension}: los clientes repartidos no son todos los que facturaron"
            );
        }
    }
}
