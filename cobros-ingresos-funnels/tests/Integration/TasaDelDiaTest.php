<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\IngresosYCobrosRepository;
use App\Repositories\NotaCreditoRepository;
use App\Repositories\PagoRepository;
use App\Repositories\SegmentacionRepository;
use PDOException;

/**
 * Cada boleta, pago y nota de credito guarda la tasa de cambio de su dia (migracion
 * 011): cambiar monedas.tasa_a_usd despues no mueve los ingresos ni los cobros ya
 * cargados. Solo la cartera pendiente se valua a la tasa de hoy.
 *
 * Los tests usan una moneda sin ningun movimiento en la base y fechas de 2090 en
 * adelante, asi cada total es exactamente lo que el test cargo, sin depender del seed.
 * Todo se deshace con la transaccion del test, tasas incluidas.
 */
final class TasaDelDiaTest extends IntegracionTestCase
{
    private string $moneda;
    private int $clienteId;

    protected function setUp(): void
    {
        parent::setUp();
        $moneda = Database::connection()->query(
            "SELECT m.codigo FROM monedas m
             WHERE m.codigo <> 'USD'
               AND NOT EXISTS (SELECT 1 FROM boletas b WHERE b.moneda_codigo = m.codigo)
               AND NOT EXISTS (SELECT 1 FROM pagos p WHERE p.moneda_codigo = m.codigo)
               AND NOT EXISTS (SELECT 1 FROM notas_credito n WHERE n.moneda_codigo = m.codigo)
             ORDER BY m.codigo LIMIT 1"
        )->fetchColumn();
        self::assertIsString($moneda, 'hace falta una moneda del catalogo sin movimientos');
        $this->moneda = $moneda;

        $this->clienteId = (new ClienteRepository())->crear([
            'nombre' => 'Cliente de la tasa del dia',
            'email' => 'tasa-' . uniqid('', true) . '@example.com',
            'segmento' => 'general',
            'fecha_alta' => '2091-02-01',
            'pais_codigo' => 'AR',
            'ciudad' => 'Ciudad de la tasa del dia',
            'idioma' => 'Espanol',
            'genero' => 'No especifica',
            'fecha_nacimiento' => '1990-05-05',
        ]);
    }

    private function fijarTasa(string $tasa): void
    {
        Database::connection()->prepare('UPDATE monedas SET tasa_a_usd = :tasa WHERE codigo = :codigo')
            ->execute([':tasa' => $tasa, ':codigo' => $this->moneda]);
    }

    private function boleta(string $monto = '1000.00', string $emision = '2090-03-15', string $vencimiento = '2090-04-15'): int
    {
        return (new BoletaRepository())->crear([
            'cliente_id' => $this->clienteId,
            'concepto' => 'Boleta de la tasa del dia',
            'monto' => $monto,
            'moneda_codigo' => $this->moneda,
            'fecha_emision' => $emision,
            'fecha_vencimiento' => $vencimiento,
        ]);
    }

    private function pago(?int $boletaId, string $monto, string $fecha = '2090-03-20', string $metodo = 'transferencia'): int
    {
        return (new PagoRepository())->crear([
            'boleta_id' => $boletaId,
            'cliente_id' => $this->clienteId,
            'monto' => $monto,
            'moneda_codigo' => $this->moneda,
            'fecha_pago' => $fecha,
            'metodo' => $metodo,
        ]);
    }

    private function tasaGrabada(string $tabla, int $id): string
    {
        // El nombre de la tabla es siempre uno de los tres que fija cada test.
        $stmt = Database::connection()->prepare("SELECT tasa_a_usd FROM {$tabla} WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return (string) $stmt->fetchColumn();
    }

    public function testCadaFilaNuevaGrabaLaTasaDeSuMonedaDeEseMomento(): void
    {
        $this->fijarTasa('1.25000000');
        $boleta = $this->boleta();
        $pago = $this->pago($boleta, '400.00');
        $nota = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta, 'cliente_id' => $this->clienteId, 'monto' => '10.00',
            'moneda_codigo' => $this->moneda, 'fecha' => '2090-03-25', 'motivo' => 'Prueba',
        ]);

        self::assertSame('1.250000000000', $this->tasaGrabada('boletas', $boleta));
        self::assertSame('1.250000000000', $this->tasaGrabada('pagos', $pago));
        self::assertSame('1.250000000000', $this->tasaGrabada('notas_credito', $nota), 'con el pago a 1.25, la nota tambien');
    }

    public function testCambiarLaTasaDeLaMonedaNoTocaLasFilasYaCargadas(): void
    {
        $this->fijarTasa('1.25000000');
        $boleta = $this->boleta();
        $pago = $this->pago($boleta, '400.00');

        $this->fijarTasa('2.00000000');
        $otraBoleta = $this->boleta();

        self::assertSame('1.250000000000', $this->tasaGrabada('boletas', $boleta));
        self::assertSame('1.250000000000', $this->tasaGrabada('pagos', $pago));
        self::assertSame('2.000000000000', $this->tasaGrabada('boletas', $otraBoleta), 'la fila nueva toma la tasa nueva');
    }

    public function testEditarUnaBoletaOUnPagoNoLosVuelveACotizar(): void
    {
        $this->fijarTasa('1.25000000');
        $boleta = $this->boleta();
        $pago = $this->pago($boleta, '400.00');
        $this->fijarTasa('2.00000000');

        (new BoletaRepository())->actualizar($boleta, [
            'concepto' => 'Corregida', 'monto' => '1200.00', 'fecha_emision' => '2090-03-16', 'fecha_vencimiento' => '2090-04-16',
        ]);
        (new PagoRepository())->actualizar($pago, ['monto' => '450.00', 'fecha_pago' => '2090-03-21', 'metodo' => 'tarjeta']);

        self::assertSame('1.250000000000', $this->tasaGrabada('boletas', $boleta));
        self::assertSame('1.250000000000', $this->tasaGrabada('pagos', $pago));
    }

    public function testUnaTasaExplicitaSeRespeta(): void
    {
        $this->fijarTasa('1.25000000');
        $stmt = Database::connection()->prepare(
            'INSERT INTO boletas (cliente_id, concepto, monto, moneda_codigo, fecha_emision, fecha_vencimiento, tasa_a_usd)
             VALUES (:c, :concepto, 100, :moneda, :e, :v, 0.5) RETURNING id'
        );
        $stmt->execute([':c' => $this->clienteId, ':concepto' => 'Con su propia tasa', ':moneda' => $this->moneda, ':e' => '2090-03-15', ':v' => '2090-04-15']);

        self::assertSame('0.500000000000', $this->tasaGrabada('boletas', (int) $stmt->fetchColumn()));
    }

    public function testLaTasaGrabadaTieneQueSerPositiva(): void
    {
        foreach (['boletas', 'pagos', 'notas_credito'] as $tabla) {
            foreach (['0', '-1'] as $tasa) {
                try {
                    Database::transaccion(fn (): bool => $this->insertarConTasa($tabla, $tasa));
                    self::fail("{$tabla} aceptaba la tasa {$tasa}");
                } catch (PDOException $e) {
                    self::assertSame('23514', (string) $e->getCode(), "{$tabla} con la tasa {$tasa}: check_violation");
                }
            }
        }
    }

    private function insertarConTasa(string $tabla, string $tasa): bool
    {
        $db = Database::connection();
        $boleta = $this->boleta();
        $sql = match ($tabla) {
            'boletas' => "INSERT INTO boletas (cliente_id, concepto, monto, moneda_codigo, fecha_emision, fecha_vencimiento, tasa_a_usd)
                          VALUES ({$this->clienteId}, 'x', 10, '{$this->moneda}', '2090-03-15', '2090-04-15', {$tasa})",
            'pagos' => "INSERT INTO pagos (boleta_id, cliente_id, monto, moneda_codigo, fecha_pago, metodo, tasa_a_usd)
                        VALUES ({$boleta}, {$this->clienteId}, 10, '{$this->moneda}', '2090-03-20', 'efectivo', {$tasa})",
            default => "INSERT INTO notas_credito (boleta_id, cliente_id, monto, moneda_codigo, fecha, motivo, tasa_a_usd)
                        VALUES ({$boleta}, {$this->clienteId}, 10, '{$this->moneda}', '2090-03-25', 'x', {$tasa})",
        };

        return $db->exec($sql) !== false;
    }

    public function testUnaMonedaQueNoExisteSigueDandoElErrorDeLaClaveForanea(): void
    {
        try {
            Database::transaccion(fn (): int => (new BoletaRepository())->crear([
                'cliente_id' => $this->clienteId, 'concepto' => 'x', 'monto' => '10.00', 'moneda_codigo' => 'ZZZ',
                'fecha_emision' => '2090-03-15', 'fecha_vencimiento' => '2090-04-15',
            ]));
            self::fail('una moneda que no existe no puede entrar');
        } catch (PDOException $e) {
            self::assertSame('23503', (string) $e->getCode(), 'foreign_key_violation');
        }
    }

    public function testLosIngresosYLosCobrosDeUnMesCerradoNoSeMuevenConLaTasa(): void
    {
        $repo = new IngresosYCobrosRepository();
        $this->fijarTasa('1.25000000');
        $boleta = $this->boleta('1000.00');
        $this->pago($boleta, '400.00', metodo: 'tarjeta');

        $kpis = $repo->kpis('2090-03-01', '2090-03-31');
        self::assertEqualsWithDelta(1250.0, $kpis['facturado'], 0.001);
        self::assertEqualsWithDelta(500.0, $kpis['cobrado'], 0.001);
        $ingresos = $repo->ingresosPorMes('2090-03-01', '2090-03-31');
        $cobros = $repo->cobrosPorMes('2090-03-01', '2090-03-31');
        $metodos = $repo->porMetodo('2090-03-01', '2090-03-31');

        $this->fijarTasa('2.00000000');   // la moneda se duplica: el pasado no cambia

        self::assertEquals($kpis, $repo->kpis('2090-03-01', '2090-03-31'));
        self::assertEquals($ingresos, $repo->ingresosPorMes('2090-03-01', '2090-03-31'));
        self::assertEquals($cobros, $repo->cobrosPorMes('2090-03-01', '2090-03-31'));
        self::assertEquals($metodos, $repo->porMetodo('2090-03-01', '2090-03-31'));
        self::assertEqualsWithDelta(1250.0, (float) $ingresos[0]['total'], 0.001);
        self::assertEqualsWithDelta(500.0, $cobros[0]['total'], 0.001);
        self::assertSame('tarjeta', $metodos[0]['metodo']);
        self::assertEqualsWithDelta(500.0, (float) $metodos[0]['total'], 0.001);
    }

    public function testCadaMesSeConvierteConLaTasaDeSuMomento(): void
    {
        $repo = new IngresosYCobrosRepository();
        $this->fijarTasa('1.00000000');
        $this->boleta('1000.00', '2090-05-10', '2090-06-10');
        $this->fijarTasa('3.00000000');
        $this->boleta('1000.00', '2090-06-10', '2090-07-10');

        $porMes = array_column($repo->ingresosPorMes('2090-05-01', '2090-06-30'), 'total', 'mes');

        self::assertEqualsWithDelta(1000.0, (float) $porMes['2090-05'], 0.001);
        self::assertEqualsWithDelta(3000.0, (float) $porMes['2090-06'], 0.001);
    }

    /** La cartera es plata por cobrar: se valua a la tasa de hoy, y es lo unico en USD que se mueve con ella. */
    public function testLaCarteraPendienteSiSeValuaALaTasaDeHoy(): void
    {
        $repo = new IngresosYCobrosRepository();
        $this->fijarTasa('1.25000000');
        $boleta = $this->boleta('1000.00', date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));
        $this->pago($boleta, '400.00', date('Y-m-d'));

        $antes = $repo->carteraPorAntiguedad();
        $this->fijarTasa('2.00000000');
        $despues = $repo->carteraPorAntiguedad();

        self::assertEqualsWithDelta(
            600.0 * (2.0 - 1.25),
            $despues['Al día'] - $antes['Al día'],
            0.05,
            'los 600 que faltan cobrar valen 0.75 mas por unidad que antes'
        );
        self::assertEqualsWithDelta($antes['1-30 días'], $despues['1-30 días'], 0.001);
    }

    /**
     * Un pago cobrado a 0.80 y devuelto cuando la tasa ya esta en 1.00 tiene que
     * restar lo mismo que sumo. Con la tasa de hoy en la nota, devolver 400 daria
     * 400 en lugar de 320 y el "cobrado" del mes quedaria negativo en 80 dolares que
     * nadie cobro.
     */
    public function testLaNotaDeCreditoTomaElPromedioDeLosPagosQueDevuelve(): void
    {
        $repo = new IngresosYCobrosRepository();
        $this->fijarTasa('0.80000000');
        $boleta = $this->boleta('1000.00');
        $this->pago($boleta, '100.00');
        $this->fijarTasa('1.00000000');
        $this->pago($boleta, '300.00');

        $this->fijarTasa('1.50000000');   // cuando se anula, la tasa de hoy es otra
        $nota = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta, 'cliente_id' => $this->clienteId, 'monto' => '400.00',
            'moneda_codigo' => $this->moneda, 'fecha' => '2090-03-25', 'motivo' => 'Boleta anulada',
        ]);

        // (100 * 0.80 + 300 * 1.00) / 400 = 0.95
        self::assertSame('0.950000000000', $this->tasaGrabada('notas_credito', $nota));
        $kpis = $repo->kpis('2090-03-01', '2090-03-31');
        self::assertEqualsWithDelta(380.0, $kpis['cobrado_bruto'], 0.001, '100 * 0.80 + 300 * 1.00');
        self::assertEqualsWithDelta(380.0, $kpis['devoluciones'], 0.001, '400 * 0.95');
        self::assertEqualsWithDelta(0.0, $kpis['cobrado'], 0.001, 'el cobro y la devolucion se cancelan');
        self::assertEqualsWithDelta(0.0, $repo->cobrosPorMes('2090-03-01', '2090-03-31')[0]['total'], 0.001);
    }

    public function testLaNotaDeCreditoNoCuentaLosPagosAnulados(): void
    {
        $this->fijarTasa('1.00000000');
        $boleta = $this->boleta('1000.00');
        $this->pago($boleta, '100.00');
        $anulado = $this->pago($boleta, '300.00');
        Database::connection()->prepare('UPDATE pagos SET anulada = TRUE, tasa_a_usd = 9 WHERE id = :id')->execute([':id' => $anulado]);

        $nota = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta, 'cliente_id' => $this->clienteId, 'monto' => '100.00',
            'moneda_codigo' => $this->moneda, 'fecha' => '2090-03-25', 'motivo' => 'Prueba',
        ]);

        self::assertSame('1.000000000000', $this->tasaGrabada('notas_credito', $nota), 'el pago anulado, con su tasa de 9, no pesa');
    }

    public function testUnaNotaSobreUnaBoletaSinPagosTomaLaTasaDelDia(): void
    {
        $this->fijarTasa('1.75000000');
        $boleta = $this->boleta();

        $nota = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta, 'cliente_id' => $this->clienteId, 'monto' => '10.00',
            'moneda_codigo' => $this->moneda, 'fecha' => '2090-03-25', 'motivo' => 'Sin pagos',
        ]);

        self::assertSame('1.750000000000', $this->tasaGrabada('notas_credito', $nota));
    }

    public function testElLtvYLaFacturacionPorClienteUsanLaTasaDeCadaFila(): void
    {
        $repo = new SegmentacionRepository();
        $this->fijarTasa('1.25000000');
        $boleta = $this->boleta('1000.00');
        $this->pago($boleta, '400.00');
        $this->pago(null, '100.00');   // un anticipo, sin boleta

        $ltv = static fn (): array => array_column((new SegmentacionRepository())->ltvPorCohorte(), 'ltv_promedio', 'cohorte');
        $ciudad = static function () use ($repo): array {
            return array_column($repo->topPorDimensiones(1000000)['ciudad'], 'total_facturado', 'etiqueta');
        };

        self::assertEqualsWithDelta(625.0, (float) $ltv()['2091-02'], 0.001, '(400 + 100) * 1.25');
        self::assertEqualsWithDelta(1250.0, (float) $ciudad()['Ciudad de la tasa del dia'], 0.001);

        $this->fijarTasa('4.00000000');

        self::assertEqualsWithDelta(625.0, (float) $ltv()['2091-02'], 0.001, 'el LTV de la cohorte no cambia');
        self::assertEqualsWithDelta(1250.0, (float) $ciudad()['Ciudad de la tasa del dia'], 0.001, 'tampoco lo facturado por el cliente');
    }

    public function testElLtvDescuentaLasNotasDeCreditoConSuPropiaTasa(): void
    {
        $this->fijarTasa('1.25000000');
        $boleta = $this->boleta('1000.00');
        $this->pago($boleta, '400.00');
        $this->fijarTasa('3.00000000');
        (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta, 'cliente_id' => $this->clienteId, 'monto' => '400.00',
            'moneda_codigo' => $this->moneda, 'fecha' => '2090-03-25', 'motivo' => 'Boleta anulada',
        ]);

        $ltv = array_column((new SegmentacionRepository())->ltvPorCohorte(), 'ltv_promedio', 'cohorte');

        self::assertEqualsWithDelta(0.0, (float) $ltv['2091-02'], 0.001, 'cobrado y devuelto a la misma tasa: el cliente no dejo nada');
    }

    /** Lo que ya existia al aplicar la migracion quedo con la tasa que su moneda tenia en ese momento. */
    public function testNingunaFilaQuedaSinTasa(): void
    {
        $db = Database::connection();
        foreach (['boletas', 'pagos', 'notas_credito'] as $tabla) {
            self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM {$tabla} WHERE tasa_a_usd IS NULL OR tasa_a_usd <= 0")->fetchColumn(), $tabla);
        }
    }

    public function testLaColumnaNoSePuedeDejarEnNull(): void
    {
        $this->fijarTasa('1.25000000');
        $boleta = $this->boleta();

        try {
            Database::transaccion(fn (): bool => Database::connection()->prepare('UPDATE boletas SET tasa_a_usd = NULL WHERE id = :id')->execute([':id' => $boleta]));
            self::fail('la tasa grabada no puede borrarse');
        } catch (PDOException $e) {
            self::assertSame('23502', (string) $e->getCode(), 'not_null_violation');
        }
        self::assertSame('1.250000000000', $this->tasaGrabada('boletas', $boleta));
    }

    /** El seed y los scripts insertan con SQL directo: tambien quedan cotizados, sin acordarse de nada. */
    public function testUnInsertDirectoSinTasaTambienQuedaCotizado(): void
    {
        $this->fijarTasa('1.25000000');
        $stmt = Database::connection()->prepare(
            'INSERT INTO pagos (cliente_id, monto, moneda_codigo, fecha_pago, metodo)
             VALUES (:c, 50, :moneda, :f, :m) RETURNING id'
        );
        $stmt->execute([':c' => $this->clienteId, ':moneda' => $this->moneda, ':f' => '2090-03-20', ':m' => 'efectivo']);

        self::assertSame('1.250000000000', $this->tasaGrabada('pagos', (int) $stmt->fetchColumn()));
    }
}
