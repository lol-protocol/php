<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\FlujoDeCajaRepository;
use App\Repositories\IngresosYCobrosRepository;
use App\Repositories\PagoRepository;
use DateTimeImmutable;

/**
 * El flujo de caja con el supuesto del atraso habitual: cada boleta por vencer se espera el dia de su vencimiento mas lo
 * que ese cliente suele atrasarse. Cada test arma el historial de un cliente (boletas ya pagadas, con los dias de atraso
 * que se piden) y UNA boleta abierta que vence el ultimo dia de este mes: con cualquier atraso de uno o mas dias se
 * espera cobrar el mes que viene, asi que el resultado no depende del dia en que corra la prueba. Se mide lo que
 * cambia entre dos fotos (el seed ya trae boletas), en USD a tasa 1.
 */
final class FlujoDeCajaConAtrasoTest extends IntegracionTestCase
{
    private DateTimeImmutable $hoy;
    private DateTimeImmutable $finDeMes;
    private string $esteMes;
    private string $mesQueViene;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoy = new DateTimeImmutable('today');
        $this->finDeMes = new DateTimeImmutable('last day of this month');
        $this->esteMes = $this->hoy->format('Y-m');
        $this->mesQueViene = $this->hoy->modify('first day of next month')->format('Y-m');
    }

    private function cliente(): int
    {
        return (new ClienteRepository())->crear([
            'nombre' => 'Cliente del atraso', 'email' => 'atraso-' . uniqid() . '@example.com', 'segmento' => 'general',
            'fecha_alta' => $this->hoy->format('Y-m-d'), 'pais_codigo' => 'US', 'ciudad' => 'Miami', 'idioma' => 'Ingles',
            'genero' => 'No especifica', 'fecha_nacimiento' => '1980-01-01',
        ]);
    }

    private function boleta(int $cliente, DateTimeImmutable $vence, float $monto = 100.0): int
    {
        return (new BoletaRepository())->crear([
            'cliente_id' => $cliente, 'concepto' => 'Boleta del atraso', 'monto' => $monto, 'moneda_codigo' => 'USD',
            'fecha_emision' => '2020-01-01', 'fecha_vencimiento' => $vence->format('Y-m-d'),
        ]);
    }

    private function pago(int $cliente, int $boleta, float $monto, DateTimeImmutable $fecha, bool $anulado = false): void
    {
        $id = (new PagoRepository())->crear([
            'boleta_id' => $boleta, 'cliente_id' => $cliente, 'monto' => $monto, 'moneda_codigo' => 'USD',
            'fecha_pago' => $fecha->format('Y-m-d'), 'metodo' => 'transferencia',
        ]);
        if ($anulado) {
            Database::connection()->prepare('UPDATE pagos SET anulada = TRUE WHERE id = :id')->execute([':id' => $id]);
        }
    }

    /**
     * Una boleta de 100 que vencio hace 200 dias y se pago completa $dias dias despues de su vencimiento (negativo: antes).
     * Es una boleta del historial del cliente, no una abierta.
     */
    private function boletaPagada(int $cliente, int $dias): int
    {
        $vence = $this->hoy->modify('-200 days');
        $boleta = $this->boleta($cliente, $vence);
        $this->pago($cliente, $boleta, 100.0, $vence->modify(sprintf('%+d days', $dias)));

        return $boleta;
    }

    /** Un cliente con tantas boletas pagadas como atrasos se den. */
    private function clienteConHistorial(int ...$atrasos): int
    {
        $cliente = $this->cliente();
        foreach ($atrasos as $dias) {
            $this->boletaPagada($cliente, $dias);
        }

        return $cliente;
    }

    /**
     * Las cubetas con el supuesto pedido, como clave => saldo.
     *
     * @return array<string, float>
     */
    private function saldos(string $supuesto, int $meses = 6): array
    {
        $saldos = [];
        foreach ((new FlujoDeCajaRepository())->cubetas($meses, $supuesto) as $cubeta) {
            $saldos[$cubeta['clave']] = $cubeta['saldo_usd'];
        }

        return $saldos;
    }

    /**
     * Lo que sumo cada cubeta entre dos fotos (solo las que cambiaron).
     *
     * @param array<string, float> $antes
     * @param array<string, float> $despues
     * @return array<string, float>
     */
    private static function cambios(array $antes, array $despues): array
    {
        $cambios = [];
        foreach ($despues as $clave => $saldo) {
            $dif = round($saldo - $antes[$clave], 2);
            if ($dif !== 0.0) {
                $cambios[$clave] = $dif;
            }
        }

        return $cambios;
    }

    /** Agrega la boleta abierta (la que vence a fin de mes) y dice en que cubeta cae con cada supuesto. */
    private function dondeCae(int $cliente, float $monto = 100.0, int $meses = 6): array
    {
        $antesVencimiento = $this->saldos(FlujoDeCajaRepository::SUPUESTO_VENCIMIENTO, $meses);
        $antesAtraso = $this->saldos(FlujoDeCajaRepository::SUPUESTO_ATRASO, $meses);

        $this->boleta($cliente, $this->finDeMes, $monto);

        return [
            'vencimiento' => self::cambios($antesVencimiento, $this->saldos(FlujoDeCajaRepository::SUPUESTO_VENCIMIENTO, $meses)),
            'atraso' => self::cambios($antesAtraso, $this->saldos(FlujoDeCajaRepository::SUPUESTO_ATRASO, $meses)),
        ];
    }

    public function testElAtrasoHabitualMueveLaBoletaAlMesEnQueSeEsperaCobrarla(): void
    {
        $cliente = $this->clienteConHistorial(10, 20, 30);   // se atrasa 20 dias en promedio

        $cae = $this->dondeCae($cliente);

        self::assertSame([$this->esteMes => 100.0], $cae['vencimiento'], 'con el supuesto del vencimiento, este mes');
        self::assertSame([$this->mesQueViene => 100.0], $cae['atraso'], 'fin de mes + 20 dias: el mes que viene');
    }

    /** El promedio se redondea al dia mas cercano (ni se trunca ni se sube siempre): lo notan los atrasos de 0 y 1 dia. */
    public function testElPromedioDeAtrasoSeRedondeaAlDiaMasCercano(): void
    {
        $masDeLaMitad = $this->clienteConHistorial(0, 1, 1);    // 0,67 dias: redondea a 1, y 1 dia ya pasa a fin de mes
        $menosDeLaMitad = $this->clienteConHistorial(0, 0, 1);  // 0,33 dias: redondea a 0, se queda en este mes

        self::assertSame([$this->mesQueViene => 100.0], $this->dondeCae($masDeLaMitad)['atraso'], '0,67 → 1 dia');
        self::assertSame([$this->esteMes => 100.0], $this->dondeCae($menosDeLaMitad)['atraso'], '0,33 → 0 dias');
    }

    public function testConMenosDeTresBoletasPagadasSeEsperaElDiaDelVencimiento(): void
    {
        $cliente = $this->clienteConHistorial(40, 40);   // dos: no hay habito, hay azar

        $cae = $this->dondeCae($cliente);

        self::assertSame([$this->esteMes => 100.0], $cae['atraso']);
        self::assertSame($cae['vencimiento'], $cae['atraso']);
    }

    public function testElTercerPagoYaDaHistorial(): void
    {
        $cliente = $this->clienteConHistorial(20, 20, 20);

        self::assertSame([$this->mesQueViene => 100.0], $this->dondeCae($cliente)['atraso'], 'con tres boletas pagadas, el atraso cuenta');
    }

    /** Pagar antes no compensa los atrasos: el promedio de -50, 10 y 10 seria -10 (se esperaria cobrar antes de tiempo); con los adelantos en cero es 7. */
    public function testUnPagoAnticipadoCuentaCeroYNoCompensaLosAtrasos(): void
    {
        $cliente = $this->clienteConHistorial(-50, 10, 10);

        self::assertSame([$this->mesQueViene => 100.0], $this->dondeCae($cliente)['atraso'], '(0 + 10 + 10) / 3 = 7 dias: el mes que viene; con signo (-10) caeria este mes');
    }

    public function testUnClienteSinAtrasoNoSeMueve(): void
    {
        $cliente = $this->clienteConHistorial(0, 0, 0);

        self::assertSame([$this->esteMes => 100.0], $this->dondeCae($cliente)['atraso']);
    }

    public function testSoloCuentanLasBoletasPagadasCompletasYVigentes(): void
    {
        $cliente = $this->clienteConHistorial(0, 0, 0);
        // Una pagada a medias, muy atrasada: no esta terminada de pagar, su atraso no se sabe.
        $parcial = $this->boleta($cliente, $this->hoy->modify('-200 days'));
        $this->pago($cliente, $parcial, 40.0, $this->hoy->modify('-100 days'));
        // Una anulada pero saldada con 60 dias de atraso: es una boleta que no existe.
        $anulada = $this->boletaPagada($cliente, 60);
        Database::connection()->prepare('UPDATE boletas SET anulada = TRUE WHERE id = :id')->execute([':id' => $anulada]);
        // Una cuyo unico pago se anulo: ya no esta saldada.
        $conPagoAnulado = $this->boleta($cliente, $this->hoy->modify('-200 days'));
        $this->pago($cliente, $conPagoAnulado, 100.0, $this->hoy->modify('-100 days'), anulado: true);

        self::assertSame([$this->esteMes => 100.0], $this->dondeCae($cliente)['atraso'], 'ninguna de las tres cuenta: sigue sin atraso');
    }

    public function testUnaBoletaSaldadaEnVariosPagosSeAtrasaHastaElUltimo(): void
    {
        $cliente = $this->clienteConHistorial(0, 0);
        $vence = $this->hoy->modify('-200 days');
        $boleta = $this->boleta($cliente, $vence);
        $this->pago($cliente, $boleta, 60.0, $vence);                          // a tiempo
        $this->pago($cliente, $boleta, 40.0, $vence->modify('+60 days'));       // el resto, 60 dias tarde: la boleta se salda ahi

        // (0 + 0 + 60) / 3 = 20 dias.
        self::assertSame([$this->mesQueViene => 100.0], $this->dondeCae($cliente)['atraso']);
    }

    public function testLoYaVencidoSigueEnSuCubetaEnLosDosSupuestos(): void
    {
        $cliente = $this->clienteConHistorial(20, 20, 20);
        $antesVencimiento = $this->saldos(FlujoDeCajaRepository::SUPUESTO_VENCIMIENTO);
        $antesAtraso = $this->saldos(FlujoDeCajaRepository::SUPUESTO_ATRASO);

        $this->boleta($cliente, $this->hoy->modify('-5 days'), 70.0);

        self::assertSame([FlujoDeCajaRepository::VENCIDO => 70.0], self::cambios($antesVencimiento, $this->saldos(FlujoDeCajaRepository::SUPUESTO_VENCIMIENTO)));
        self::assertSame([FlujoDeCajaRepository::VENCIDO => 70.0], self::cambios($antesAtraso, $this->saldos(FlujoDeCajaRepository::SUPUESTO_ATRASO)), 'no se le pone fecha esperada a lo que ya no se cobro cuando tocaba');
    }

    public function testUnAtrasoQueSaleDelHorizontePasaAMasAdelante(): void
    {
        $cliente = $this->clienteConHistorial(100, 100, 100);   // fin de mes + 100 dias: unos tres meses y medio

        $cae = $this->dondeCae($cliente, 100.0, 3);

        self::assertSame([$this->esteMes => 100.0], $cae['vencimiento']);
        self::assertSame([FlujoDeCajaRepository::DESPUES => 100.0], $cae['atraso'], 'pasa del horizonte de 3 meses');
    }

    public function testElSupuestoNoCambiaCuantoSeCobraSoloEnQueMes(): void
    {
        $this->clienteConHistorial(0, 0, 0);
        $conHistorial = $this->clienteConHistorial(30, 30, 30);
        $this->boleta($conHistorial, $this->finDeMes, 100.0);
        $this->boleta($conHistorial, $this->hoy->modify('+40 days'), 55.5);
        $sinHistorial = $this->cliente();
        $this->boleta($sinHistorial, $this->hoy->modify('+10 days'), 12.25);
        $this->boleta($sinHistorial, $this->hoy->modify('-3 days'), 9.0);

        $repo = new FlujoDeCajaRepository();
        $carteraDelDashboard = array_sum((new IngresosYCobrosRepository())->carteraPorAntiguedad());
        foreach ([3, 6, 12] as $meses) {
            $conAtraso = FlujoDeCajaRepository::totales($repo->cubetas($meses, FlujoDeCajaRepository::SUPUESTO_ATRASO));
            $alVencimiento = FlujoDeCajaRepository::totales($repo->cubetas($meses, FlujoDeCajaRepository::SUPUESTO_VENCIMIENTO));

            self::assertEqualsWithDelta($alVencimiento['total_usd'], $conAtraso['total_usd'], 0.001, "{$meses} meses: el supuesto solo cambia en que mes cae cada boleta");
            self::assertEqualsWithDelta($carteraDelDashboard, $conAtraso['total_usd'], 0.001, "{$meses} meses: y la suma es la cartera pendiente del Dashboard");
            self::assertSame($alVencimiento['boletas'], $conAtraso['boletas']);
            self::assertEqualsWithDelta($alVencimiento['vencido_usd'], $conAtraso['vencido_usd'], 0.001, 'lo vencido no se mueve');
        }
    }

    public function testLoQueSeMovioPorElAtrasoSeCuentaAparte(): void
    {
        $repo = new FlujoDeCajaRepository();
        $antes = FlujoDeCajaRepository::totales($repo->cubetas())['con_historial_usd'];
        $conHistorial = $this->clienteConHistorial(30, 30, 30);
        $this->boleta($conHistorial, $this->finDeMes, 100.0);              // por vencer, con historial: cuenta
        $this->boleta($conHistorial, $this->hoy->modify('-2 days'), 40.0); // vencida: no cuenta
        $sinHistorial = $this->cliente();
        $this->boleta($sinHistorial, $this->finDeMes, 25.0);               // por vencer, sin historial: no cuenta

        $conAtraso = FlujoDeCajaRepository::totales($repo->cubetas(6, FlujoDeCajaRepository::SUPUESTO_ATRASO));
        $alVencimiento = FlujoDeCajaRepository::totales($repo->cubetas(6, FlujoDeCajaRepository::SUPUESTO_VENCIMIENTO));

        self::assertEqualsWithDelta($antes + 100.0, $conAtraso['con_historial_usd'], 0.001);
        self::assertSame(0.0, $alVencimiento['con_historial_usd'], 'con el supuesto del vencimiento nada se mueve');
        self::assertEqualsWithDelta($conAtraso['total_usd'] - $conAtraso['vencido_usd'], $conAtraso['por_vencer_usd'], 0.001);
    }

    public function testUnSupuestoQueNoExisteSeTomaComoElDelVencimiento(): void
    {
        $cliente = $this->clienteConHistorial(30, 30, 30);
        $antes = $this->saldos('invento');
        $this->boleta($cliente, $this->finDeMes, 100.0);

        self::assertSame([$this->esteMes => 100.0], self::cambios($antes, $this->saldos('invento')));
    }
}
