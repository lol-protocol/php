<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\FunnelRepository;
use App\Repositories\SegmentacionRepository;

/**
 * Lo que vale cada canal de adquisicion: de los usuarios que visitaron en el periodo, lo que cobraron los que se
 * volvieron clientes. Cada test arma sus datos en marzo de 2019 (los de ejemplo son de este ultimo ano, asi que no
 * se mezclan) y se deshace con la transaccion.
 *
 * Los pagos y las notas se insertan a mano para fijar la tasa de cada uno: en la app la toma la base del dia.
 */
final class IngresosPorCanalTest extends IntegracionTestCase
{
    private const DESDE = '2019-03-01';
    private const HASTA = '2019-03-31';

    private int $secuencia = 0;

    private function cliente(string $alta = '2019-03-10'): int
    {
        return (new ClienteRepository())->crear([
            'nombre' => 'Cliente ' . ++$this->secuencia, 'email' => 'canal-' . uniqid() . '@example.com', 'segmento' => 'general',
            'fecha_alta' => $alta, 'pais_codigo' => 'US', 'ciudad' => 'Miami', 'idioma' => 'Ingles',
            'genero' => 'No especifica', 'fecha_nacimiento' => '1985-05-05',
        ]);
    }

    /** Un usuario del funnel con su visita y, si $convertido, todas las etapas ese mismo dia (si no, solo la visita). */
    private function usuario(string $canal, string $visita, bool $convertido = false, ?int $clienteId = null): void
    {
        Database::connection()->prepare(
            "INSERT INTO usuarios_funnel (nombre, email, canal, pais_codigo, ciudad, idioma, genero, rango_de_edad,
                                          fecha_visita, fecha_registro, fecha_lead, fecha_conversion, cliente_id)
             VALUES (:nombre, :email, :canal, 'US', 'Miami', 'Ingles', 'No especifica', '35-44',
                     :visita, :etapa, :etapa, :etapa, :cliente)"
        )->execute([
            ':nombre' => 'Usuario ' . ++$this->secuencia, ':email' => 'u-' . uniqid() . '@example.com', ':canal' => $canal,
            ':visita' => $visita, ':etapa' => $convertido ? $visita : null, ':cliente' => $clienteId,
        ]);
    }

    /** Un pago (anticipo, sin boleta) de $monto en $moneda a la $tasa que guardo al crearse. */
    private function pago(int $clienteId, float $monto, string $moneda = 'USD', float $tasa = 1.0, bool $anulado = false): void
    {
        Database::connection()->prepare(
            "INSERT INTO pagos (boleta_id, cliente_id, monto, moneda_codigo, fecha_pago, metodo, tasa_a_usd, anulada)
             VALUES (NULL, :cliente, :monto, :moneda, '2019-04-01', 'tarjeta', :tasa, :anulada)"
        )->execute([':cliente' => $clienteId, ':monto' => $monto, ':moneda' => $moneda, ':tasa' => $tasa, ':anulada' => $anulado ? 'true' : 'false']);
    }

    /** Una nota de credito de $monto dolares (necesita una boleta a la que colgarse). */
    private function nota(int $clienteId, float $monto): void
    {
        $boletaId = (new BoletaRepository())->crear([
            'cliente_id' => $clienteId, 'concepto' => 'Boleta de la nota', 'monto' => 1000, 'moneda_codigo' => 'USD',
            'fecha_emision' => '2019-03-10', 'fecha_vencimiento' => '2019-04-10',
        ]);
        Database::connection()->prepare(
            "INSERT INTO notas_credito (boleta_id, cliente_id, monto, moneda_codigo, fecha, motivo, tasa_a_usd)
             VALUES (:boleta, :cliente, :monto, 'USD', '2019-04-05', 'prueba', 1)"
        )->execute([':boleta' => $boletaId, ':cliente' => $clienteId, ':monto' => $monto]);
    }

    /**
     * ads: 4 visitas (una fuera del dia de corte por dentro), 2 clientes, uno con 100 USD + 100 EUR a 1.25 - 50 de nota
     * (= 175; un pago anulado de 500 no cuenta) y otro sin pagos. email: 2 visitas y 1 cliente con 40.
     * organico: 1 visita convertida SIN cliente enlazado. Y lo que no es del periodo: una visita de abril de un cliente
     * que pago 1000 y una de febrero.
     *
     * @return array<string, array<string, mixed>> las filas por canal
     */
    private function escenario(): array
    {
        $a = $this->cliente();
        $this->pago($a, 100.0);
        $this->pago($a, 100.0, 'EUR', 1.25);
        $this->pago($a, 500.0, anulado: true);
        $this->nota($a, 50.0);
        $b = $this->cliente();
        $d = $this->cliente();
        $this->pago($d, 40.0);
        $fuera = $this->cliente();
        $this->pago($fuera, 1000.0);

        $this->usuario('ads', '2019-03-05', true, $a);
        $this->usuario('ads', '2019-03-10');
        $this->usuario('ads', '2019-03-12', true, $b);
        $this->usuario('ads', '2019-03-31');
        $this->usuario('email', '2019-03-20');
        $this->usuario('email', '2019-03-01', true, $d);
        $this->usuario('organico', '2019-03-15', true, null);
        $this->usuario('ads', '2019-04-02', true, $fuera);
        $this->usuario('ads', '2019-02-28');

        $resultado = (new FunnelRepository())->ingresosPorCanal(self::DESDE, self::HASTA);

        return array_column($resultado['filas'], null, 'etiqueta');
    }

    public function testCadaCanalTraeLoCobradoDeSusClientesNetoDeNotasYASuTasa(): void
    {
        $filas = $this->escenario();

        self::assertSame(['ads', 'email', 'organico'], array_keys($filas), 'de mas a menos visitantes');
        self::assertSame([4, 2, 2, 175.0], [$filas['ads']['visitantes'], $filas['ads']['clientes'], $filas['ads']['con_cliente'], $filas['ads']['cobrado_usd']], '100 + 100 EUR a 1.25 - 50 de nota; el pago anulado y lo de abril no cuentan');
        self::assertEqualsWithDelta(87.5, $filas['ads']['ltv_usd'], 0.0001, '175 entre sus 2 clientes (uno sin pagos)');
        self::assertEqualsWithDelta(43.75, $filas['ads']['por_visitante_usd'], 0.0001, '175 entre sus 4 visitantes');
        self::assertSame([2, 1, 1, 40.0], [$filas['email']['visitantes'], $filas['email']['clientes'], $filas['email']['con_cliente'], $filas['email']['cobrado_usd']]);
        self::assertEqualsWithDelta(40.0, $filas['email']['ltv_usd'], 0.0001);
        self::assertEqualsWithDelta(20.0, $filas['email']['por_visitante_usd'], 0.0001);
    }

    public function testUnConvertidoSinClienteEnlazadoNoSumaIngresoNiEntraEnElLtv(): void
    {
        $organico = $this->escenario()['organico'];

        self::assertSame(1, $organico['clientes'], 'cuenta como cliente, igual que en la tabla de conversion');
        self::assertSame(0, $organico['con_cliente'], 'pero no hay un cliente al que atribuirle plata');
        self::assertSame(0.0, $organico['cobrado_usd']);
        self::assertNull($organico['ltv_usd'], 'sin clientes enlazados no hay LTV que dar');
        self::assertSame(0.0, $organico['por_visitante_usd']);
    }

    public function testElTotalEsLaSumaDeLosCanalesConSuPropioPromedio(): void
    {
        $this->escenario();

        $total = (new FunnelRepository())->ingresosPorCanal(self::DESDE, self::HASTA)['total'];

        self::assertNull($total['etiqueta']);
        self::assertSame([7, 4, 3, 215.0], [$total['visitantes'], $total['clientes'], $total['con_cliente'], $total['cobrado_usd']]);
        self::assertEqualsWithDelta(215 / 3, $total['ltv_usd'], 0.0001, 'el LTV total es el cobrado entre los clientes enlazados, no el promedio de los promedios');
        self::assertEqualsWithDelta(215 / 7, $total['por_visitante_usd'], 0.0001);
    }

    public function testLosBordesDelPeriodoEntranYLoDeFueraNo(): void
    {
        $this->escenario();
        $repo = new FunnelRepository();

        $soloElPrimerDia = $repo->ingresosPorCanal('2019-03-01', '2019-03-01')['total'];
        self::assertSame(1, $soloElPrimerDia['visitantes'], 'la visita del 1 de marzo entra');
        self::assertSame(40.0, $soloElPrimerDia['cobrado_usd']);

        $soloElUltimoDia = $repo->ingresosPorCanal('2019-03-31', '2019-03-31')['total'];
        self::assertSame(1, $soloElUltimoDia['visitantes'], 'la visita del 31 de marzo entra');
        self::assertSame(0, $soloElUltimoDia['clientes']);

        $abril = $repo->ingresosPorCanal('2019-04-01', '2019-04-30')['total'];
        self::assertSame(1000.0, $abril['cobrado_usd'], 'el cliente que llego en abril lleva cobrado lo suyo (hasta hoy)');
    }

    public function testUnPeriodoSinVisitasNoTienePromediosQueDar(): void
    {
        $resultado = (new FunnelRepository())->ingresosPorCanal('1999-01-01', '1999-01-31');

        self::assertSame([], $resultado['filas']);
        self::assertSame([0, 0, 0, 0.0], [$resultado['total']['visitantes'], $resultado['total']['clientes'], $resultado['total']['con_cliente'], $resultado['total']['cobrado_usd']]);
        self::assertNull($resultado['total']['ltv_usd']);
        self::assertNull($resultado['total']['por_visitante_usd']);
    }

    public function testVisitantesYClientesCuadranConLaTablaDeConversionPorCanal(): void
    {
        $this->escenario();
        $repo = new FunnelRepository();
        foreach ([[self::DESDE, self::HASTA], ['2000-01-01', '2100-12-31']] as [$desde, $hasta]) {
            $conversion = array_map(static fn (array $f): array => [$f['etiqueta'], (int) $f['visitantes'], (int) $f['clientes']], $repo->porCanal($desde, $hasta));
            $ingresos = array_map(static fn (array $f): array => [$f['etiqueta'], $f['visitantes'], $f['clientes']], $repo->ingresosPorCanal($desde, $hasta)['filas']);

            self::assertSame($conversion, $ingresos, "{$desde}..{$hasta}: las dos tablas hablan de los mismos usuarios, en el mismo orden");
        }
    }

    /** El «cobrado» de un cliente es uno solo: lo que da el LTV por cohorte de alta y lo que da el canal. */
    public function testElCobradoDeUnClienteEsElMismoEnElLtvPorCohorteYEnElCanal(): void
    {
        $cliente = $this->cliente('2018-01-15');   // una cohorte de alta propia: nadie mas se dio de alta en enero de 2018
        $this->pago($cliente, 100.0);
        $this->pago($cliente, 80.0, 'EUR', 1.25);
        $this->nota($cliente, 30.0);
        $this->usuario('referido', '2018-01-15', true, $cliente);

        $ltvDeLaCohorte = null;
        foreach ((new SegmentacionRepository())->ltvPorCohorte() as $cohorte) {
            if ($cohorte['cohorte'] === '2018-01') {
                $ltvDeLaCohorte = (float) $cohorte['ltv_promedio'];
            }
        }
        $canal = (new FunnelRepository())->ingresosPorCanal('2018-01-01', '2018-01-31')['filas'][0];

        self::assertEqualsWithDelta(170.0, $ltvDeLaCohorte, 0.0001, '100 + 80 EUR a 1.25 - 30');
        self::assertEqualsWithDelta($ltvDeLaCohorte, $canal['cobrado_usd'], 0.0001);
        self::assertEqualsWithDelta($ltvDeLaCohorte, $canal['ltv_usd'], 0.0001);
    }

    public function testClientesSinRecorridoSoloCuentaLosQueNoVinieronPorElFunnel(): void
    {
        $repo = new FunnelRepository();
        $antes = $repo->clientesSinRecorrido();

        $this->cliente();
        self::assertSame($antes + 1, $repo->clientesSinRecorrido(), 'un cliente cargado directo no tiene recorrido');

        $conRecorrido = $this->cliente();
        $this->usuario('ads', '2019-03-05', true, $conRecorrido);
        self::assertSame($antes + 1, $repo->clientesSinRecorrido(), 'uno con su usuario del funnel no suma');
    }

    /** Un cliente al que se le anulo el pago despues de la nota: solo le quedan notas, y restan (es lo que ya hacia el LTV por cohorte). */
    public function testUnClienteConSoloNotasRestaDeVerdad(): void
    {
        $cliente = $this->cliente();
        $this->pago($cliente, 100.0, anulado: true);
        $this->nota($cliente, 30.0);
        $otro = $this->cliente();
        $this->pago($otro, 100.0);
        $this->usuario('ads', '2019-03-05', true, $cliente);
        $this->usuario('ads', '2019-03-06', true, $otro);

        $fila = (new FunnelRepository())->ingresosPorCanal(self::DESDE, self::HASTA)['filas'][0];

        self::assertSame(70.0, $fila['cobrado_usd'], '100 del otro cliente - 30 de la nota del primero, que ya no tiene pagos vigentes');
    }

    public function testLosPagosSeSumanUnaVezPorClienteAunqueTengaMuchasNotas(): void
    {
        $cliente = $this->cliente();
        $this->pago($cliente, 100.0);
        $this->pago($cliente, 50.0);
        $this->nota($cliente, 10.0);
        $this->nota($cliente, 5.0);
        $this->usuario('ads', '2019-03-05', true, $cliente);

        $fila = (new FunnelRepository())->ingresosPorCanal(self::DESDE, self::HASTA)['filas'][0];

        self::assertSame(135.0, $fila['cobrado_usd'], '150 de pagos - 15 de notas: sin multiplicar filas entre pagos y notas');
    }
}
