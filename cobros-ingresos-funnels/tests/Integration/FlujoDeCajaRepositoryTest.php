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
 * El flujo de caja reparte la cartera por el mes de vencimiento. Cada test mide lo que SUMA una boleta
 * nueva (las cubetas antes y despues), en USD a tasa 1, y no el total: el seed ya trae boletas.
 */
final class FlujoDeCajaRepositoryTest extends IntegracionTestCase
{
    private DateTimeImmutable $hoy;
    private int $clienteId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoy = new DateTimeImmutable('today');
        $this->clienteId = $this->crearCliente();
    }

    private function crearCliente(): int
    {
        return (new ClienteRepository())->crear([
            'nombre' => 'Cliente del flujo',
            'email' => 'flujo-' . uniqid() . '@example.com',
            'segmento' => 'general',
            'fecha_alta' => $this->hoy->format('Y-m-d'),
            'pais_codigo' => 'US',
            'ciudad' => 'Miami',
            'idioma' => 'Ingles',
            'genero' => 'No especifica',
            'fecha_nacimiento' => '1980-01-01',
        ]);
    }

    /** Una boleta en USD (tasa 1) que vence el $vence, para $cliente (el de la prueba si no se dice). */
    private function boleta(DateTimeImmutable $vence, float $monto, ?int $cliente = null): int
    {
        return (new BoletaRepository())->crear([
            'cliente_id' => $cliente ?? $this->clienteId,
            'concepto' => 'Boleta del flujo',
            'monto' => $monto,
            'moneda_codigo' => 'USD',
            'fecha_emision' => '2020-01-01',
            'fecha_vencimiento' => $vence->format('Y-m-d'),
        ]);
    }

    /** @return array<string, array{boletas: int, clientes: int, saldo_usd: float}> las cubetas por clave */
    private function porClave(int $meses): array
    {
        $porClave = [];
        foreach ((new FlujoDeCajaRepository())->cubetas($meses) as $cubeta) {
            $porClave[$cubeta['clave']] = ['boletas' => $cubeta['boletas'], 'clientes' => $cubeta['clientes'], 'saldo_usd' => $cubeta['saldo_usd']];
        }

        return $porClave;
    }

    /**
     * Lo que sumo cada cubeta entre dos fotos (solo las que cambiaron), como clave => saldo.
     *
     * @param array<string, array{boletas: int, clientes: int, saldo_usd: float}> $antes
     * @param array<string, array{boletas: int, clientes: int, saldo_usd: float}> $despues
     * @return array<string, float>
     */
    private static function cambios(array $antes, array $despues): array
    {
        $cambios = [];
        foreach ($despues as $clave => $fila) {
            $dif = round($fila['saldo_usd'] - $antes[$clave]['saldo_usd'], 2);
            if ($dif !== 0.0) {
                $cambios[$clave] = $dif;
            }
        }

        return $cambios;
    }

    public function testSiempreTraeTodasLasCubetasEnOrden(): void
    {
        foreach ([3, 6, 12] as $meses) {
            $cubetas = (new FlujoDeCajaRepository())->cubetas($meses);

            self::assertCount($meses + 2, $cubetas, "{$meses} meses: vencido, un mes cada uno y mas adelante");
            self::assertSame(FlujoDeCajaRepository::VENCIDO, $cubetas[0]['clave']);
            self::assertSame(FlujoDeCajaRepository::DESPUES, $cubetas[$meses + 1]['clave']);

            $primerMes = $this->hoy->modify('first day of this month');
            for ($i = 0; $i < $meses; $i++) {
                self::assertSame($primerMes->modify("+{$i} months")->format('Y-m'), $cubetas[$i + 1]['clave'], "el mes {$i} desde el actual");
            }
        }
    }

    public function testCadaBoletaCaeEnLaCubetaDeSuVencimiento(): void
    {
        $primerMes = $this->hoy->modify('first day of this month');
        $antes = $this->porClave(3);

        $this->boleta($this->hoy->modify('-1 day'), 1000);          // ya vencio
        $this->boleta($this->hoy, 2000);                             // vence hoy: todavia es de este mes
        $this->boleta($primerMes->modify('+1 month'), 4000);        // el primer dia del mes que viene
        $this->boleta($primerMes->modify('+2 months'), 8000);       // el ultimo mes del horizonte
        $this->boleta($primerMes->modify('+3 months'), 16000);      // ya fuera de un horizonte de 3
        $this->boleta($primerMes->modify('+11 months'), 32000);     // mucho mas adelante

        self::assertSame(
            [
                FlujoDeCajaRepository::VENCIDO => 1000.0,
                $primerMes->format('Y-m') => 2000.0,
                $primerMes->modify('+1 month')->format('Y-m') => 4000.0,
                $primerMes->modify('+2 months')->format('Y-m') => 8000.0,
                FlujoDeCajaRepository::DESPUES => 48000.0,
            ],
            self::cambios($antes, $this->porClave(3))
        );
    }

    public function testElHorizonteMueveLoDeMasAdelanteASuMes(): void
    {
        $vence = $this->hoy->modify('first day of this month')->modify('+4 months');
        $antes3 = $this->porClave(3);
        $antes6 = $this->porClave(6);

        $this->boleta($vence, 5000);

        self::assertSame([FlujoDeCajaRepository::DESPUES => 5000.0], self::cambios($antes3, $this->porClave(3)), 'con 3 meses queda fuera');
        self::assertSame([$vence->format('Y-m') => 5000.0], self::cambios($antes6, $this->porClave(6)), 'con 6 meses tiene su mes');
    }

    public function testUnaBoletaAnuladaOSaldadaNoEntra(): void
    {
        $vence = $this->hoy->modify('first day of this month')->modify('+1 month');
        $antes = $this->porClave(6);

        $anulada = $this->boleta($vence, 700);
        Database::connection()->prepare('UPDATE boletas SET anulada = TRUE WHERE id = :id')->execute([':id' => $anulada]);

        $saldada = $this->boleta($vence, 300);
        (new PagoRepository())->crear([
            'boleta_id' => $saldada, 'cliente_id' => $this->clienteId, 'monto' => 300, 'moneda_codigo' => 'USD',
            'fecha_pago' => '2020-02-01', 'metodo' => 'transferencia',
        ]);

        self::assertSame([], self::cambios($antes, $this->porClave(6)));
    }

    public function testUnaBoletaPagadaEnParteAportaSoloLoQueFalta(): void
    {
        $vence = $this->hoy->modify('first day of this month')->modify('+1 month');
        $antes = $this->porClave(6);

        $id = $this->boleta($vence, 1000);
        (new PagoRepository())->crear([
            'boleta_id' => $id, 'cliente_id' => $this->clienteId, 'monto' => 400, 'moneda_codigo' => 'USD',
            'fecha_pago' => '2020-02-01', 'metodo' => 'transferencia',
        ]);

        self::assertSame([$vence->format('Y-m') => 600.0], self::cambios($antes, $this->porClave(6)));
    }

    public function testLosClientesSeCuentanDistintosEnCadaCubeta(): void
    {
        $vence = $this->hoy->modify('first day of this month')->modify('+1 month');
        $otro = $this->crearCliente();
        $antes = $this->porClave(6)[$vence->format('Y-m')];

        $this->boleta($vence, 100);
        $this->boleta($vence, 200);                       // el mismo cliente, otra boleta
        $this->boleta($vence, 300, $otro);                // otro cliente

        $despues = $this->porClave(6)[$vence->format('Y-m')];
        self::assertSame(3, $despues['boletas'] - $antes['boletas']);
        self::assertSame(2, $despues['clientes'] - $antes['clientes'], 'dos clientes distintos, no tres boletas');
    }

    /**
     * Lo que cuadra con el Dashboard: la suma de las cubetas es la cartera pendiente, sea cual sea el
     * horizonte. Las dos salen de la misma consulta (saldosAbiertosSql), y esto es lo que lo prueba.
     */
    public function testSusCubetasSumanLaCarteraPendienteDelDashboard(): void
    {
        $this->boleta($this->hoy->modify('-40 days'), 1234.56);
        $this->boleta($this->hoy->modify('+20 days'), 789.01);

        $cartera = array_sum((new IngresosYCobrosRepository())->carteraPorAntiguedad());
        foreach ([3, 6, 12] as $meses) {
            $totales = FlujoDeCajaRepository::totales((new FlujoDeCajaRepository())->cubetas($meses));

            self::assertEqualsWithDelta($cartera, $totales['total_usd'], 0.05, "{$meses} meses");
        }
    }

    public function testLosTotalesSeSacanDeLasCubetas(): void
    {
        $cubetas = [
            ['clave' => 'vencido', 'boletas' => 2, 'clientes' => 2, 'saldo_usd' => 100.5],
            ['clave' => '2026-11', 'boletas' => 1, 'clientes' => 1, 'saldo_usd' => 30.0],
            ['clave' => '2026-12', 'boletas' => 4, 'clientes' => 3, 'saldo_usd' => 400.25],
            ['clave' => 'despues', 'boletas' => 1, 'clientes' => 1, 'saldo_usd' => 9.0],
        ];

        self::assertSame(
            ['total_usd' => 539.75, 'vencido_usd' => 100.5, 'este_mes_usd' => 30.0, 'boletas' => 8],
            FlujoDeCajaRepository::totales($cubetas)
        );
    }
}
