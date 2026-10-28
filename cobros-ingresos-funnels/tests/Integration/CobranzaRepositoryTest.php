<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\CobranzaRepository;
use App\Repositories\IngresosYCobrosRepository;
use App\Repositories\PagoRepository;
use DateTimeImmutable;

/**
 * La lista de cobranza contra la base. Cada test crea sus propios clientes y boletas
 * (dentro de la transaccion que se deshace al terminar) y los busca por id entre las
 * filas de todas las paginas, para no depender de los datos de ejemplo.
 */
final class CobranzaRepositoryTest extends IntegracionTestCase
{
    private const PAIS = 'US';

    private static function tasaDeHoy(): float
    {
        return (float) Database::connection()->query(
            "SELECT m.tasa_a_usd FROM paises p JOIN monedas m ON m.codigo = p.moneda_codigo WHERE p.codigo = '" . self::PAIS . "'"
        )->fetchColumn();
    }

    private static function monedaDelPais(): string
    {
        return (string) Database::connection()->query(
            "SELECT moneda_codigo FROM paises WHERE codigo = '" . self::PAIS . "'"
        )->fetchColumn();
    }

    private function clienteDePrueba(string $nombre = 'Cliente de cobranza'): int
    {
        return (new ClienteRepository())->crear([
            'nombre' => $nombre . ' ' . uniqid(),
            'email' => 'cobranza-' . uniqid() . '@example.com',
            'segmento' => 'general',
            'fecha_alta' => '2020-01-01',
            'pais_codigo' => self::PAIS,
            'ciudad' => 'Miami',
            'idioma' => 'Ingles',
            'genero' => 'No especifica',
            'fecha_nacimiento' => '1990-05-05',
        ]);
    }

    /** Una boleta del cliente que vence dentro de $diasHastaElVencimiento dias (negativo = ya vencio hace tantos). */
    private function boleta(int $clienteId, float $monto, int $diasHastaElVencimiento): int
    {
        $vence = (new DateTimeImmutable(sprintf('%+d days', $diasHastaElVencimiento)))->format('Y-m-d');

        return (new BoletaRepository())->crear([
            'cliente_id' => $clienteId,
            'concepto' => 'Boleta de cobranza ' . uniqid(),
            'monto' => $monto,
            'moneda_codigo' => self::monedaDelPais(),
            'fecha_emision' => '2020-01-01',
            'fecha_vencimiento' => $vence,
        ]);
    }

    private function pago(int $boletaId, int $clienteId, float $monto): int
    {
        return (new PagoRepository())->crear([
            'boleta_id' => $boletaId,
            'cliente_id' => $clienteId,
            'monto' => $monto,
            'moneda_codigo' => self::monedaDelPais(),
            'fecha_pago' => '2020-02-01',
            'metodo' => 'transferencia',
        ]);
    }

    /**
     * Todas las filas de la lista, de todas las paginas.
     *
     * @return list<array<string, mixed>>
     */
    private static function todasLasFilas(string $alcance, string $orden = 'vencido'): array
    {
        $repo = new CobranzaRepository();
        $filas = [];
        $pagina = 1;
        do {
            $listado = $repo->listado($alcance, $orden, $pagina);
            array_push($filas, ...$listado['filas']);
            $pagina++;
        } while ($pagina <= $listado['totalPaginas']);

        return $filas;
    }

    /** @return array<string, mixed>|null la fila de ese cliente, o null si no esta en la lista */
    private static function filaDe(int $clienteId, string $alcance = 'todos'): ?array
    {
        foreach (self::todasLasFilas($alcance) as $fila) {
            if ((int) $fila['cliente_id'] === $clienteId) {
                return $fila;
            }
        }

        return null;
    }

    public function testElSaldoDeUnClienteSumaSusBoletasAbiertasYDiceDesdeCuandoDebe(): void
    {
        $cliente = $this->clienteDePrueba();
        $this->boleta($cliente, 1000.0, -10);              // vencida hace 10 dias, sin pagos
        $parcial = $this->boleta($cliente, 500.0, 5);      // vence en 5 dias
        $this->pago($parcial, $cliente, 200.0);            // le quedan 300
        $tasa = self::tasaDeHoy();

        $fila = self::filaDe($cliente);

        self::assertNotNull($fila);
        self::assertSame(2, (int) $fila['boletas']);
        self::assertEqualsWithDelta(1300.0 * $tasa, (float) $fila['saldo_usd'], 0.01, 'el saldo es 1000 + (500 - 200), a la tasa de hoy');
        self::assertEqualsWithDelta(1000.0 * $tasa, (float) $fila['vencido_usd'], 0.01, 'solo la primera esta vencida');
        self::assertSame(10, (int) $fila['dias_mayor_vencimiento']);
        self::assertStringStartsWith('cobranza-', $fila['email']);
        self::assertSame('Miami', $fila['ciudad']);
        self::assertNotSame('', $fila['pais']);
    }

    public function testUnaBoletaPagadaAnuladaOConSuPagoAnuladoSeTrataComoCorresponde(): void
    {
        $cliente = $this->clienteDePrueba();
        $pagada = $this->boleta($cliente, 300.0, -20);
        $this->pago($pagada, $cliente, 300.0);             // saldada: no cuenta
        $anulada = $this->boleta($cliente, 700.0, -20);
        (new BoletaRepository())->anularSiEstabaActiva($anulada);   // anulada: no cuenta
        $conPagoAnulado = $this->boleta($cliente, 100.0, -20);
        $pagoAnulado = $this->pago($conPagoAnulado, $cliente, 100.0);
        (new PagoRepository())->anularSiEstabaActiva($pagoAnulado); // el pago no cuenta: debe los 100

        $fila = self::filaDe($cliente);

        self::assertNotNull($fila);
        self::assertSame(1, (int) $fila['boletas'], 'solo queda la del pago anulado');
        self::assertEqualsWithDelta(100.0 * self::tasaDeHoy(), (float) $fila['saldo_usd'], 0.01);
    }

    public function testUnClienteSinNingunaBoletaAbiertaNoApareceEnLaLista(): void
    {
        $cliente = $this->clienteDePrueba();
        $boleta = $this->boleta($cliente, 300.0, -20);
        $this->pago($boleta, $cliente, 300.0);

        self::assertNull(self::filaDe($cliente, 'todos'));
        self::assertNull(self::filaDe($cliente, 'vencidos'));
    }

    public function testElAlcanceVencidosDejaAfueraAlQueSoloDebeLoQueTodaviaNoVence(): void
    {
        $alDia = $this->clienteDePrueba();
        $this->boleta($alDia, 800.0, 15);
        $venceHoy = $this->clienteDePrueba();
        $this->boleta($venceHoy, 800.0, 0);                // vence hoy: todavia esta al dia

        foreach ([$alDia, $venceHoy] as $cliente) {
            $fila = self::filaDe($cliente, 'todos');
            self::assertNotNull($fila, 'en "todo el saldo abierto" si esta');
            self::assertEqualsWithDelta(0.0, (float) $fila['vencido_usd'], 0.0001);
            self::assertLessThanOrEqual(0, (int) $fila['dias_mayor_vencimiento']);
            self::assertNull(self::filaDe($cliente, 'vencidos'), 'en "con saldo vencido" no');
        }
    }

    public function testElOrdenSeAplicaYDesempataPorCliente(): void
    {
        // A: debe mucho pero hace poco. B: debe poco pero hace mucho. C: lo del medio.
        $a = $this->clienteDePrueba('Zeta');
        $this->boleta($a, 9000.0, -2);
        $b = $this->clienteDePrueba('Beta');
        $this->boleta($b, 100.0, -300);
        $c = $this->clienteDePrueba('Alfa');
        $this->boleta($c, 2000.0, -40);
        $ids = [$a, $b, $c];
        $posiciones = static function (string $orden) use ($ids): array {
            $enLaLista = array_values(array_filter(
                array_map(static fn (array $f): int => (int) $f['cliente_id'], self::todasLasFilas('todos', $orden)),
                static fn (int $id): bool => in_array($id, $ids, true)
            ));

            return $enLaLista;
        };

        self::assertSame([$a, $c, $b], $posiciones('vencido'), 'por monto vencido: A, C, B');
        self::assertSame([$a, $c, $b], $posiciones('saldo'), 'por saldo total: A, C, B');
        self::assertSame([$b, $c, $a], $posiciones('antiguedad'), 'por dias de vencimiento: B, C, A');
        self::assertSame([$c, $b, $a], $posiciones('cliente'), 'por nombre: Alfa, Beta, Zeta');
    }

    public function testLaListaYElResumenCuentanLoMismo(): void
    {
        $cliente = $this->clienteDePrueba();
        $this->boleta($cliente, 1500.0, -3);
        $otro = $this->clienteDePrueba();
        $this->boleta($otro, 250.0, 10);

        $resumen = (new CobranzaRepository())->resumen();
        $filas = self::todasLasFilas('todos');

        self::assertCount($resumen['clientes'], $filas, 'un cliente por fila');
        self::assertEqualsWithDelta($resumen['saldo_usd'], array_sum(array_column($filas, 'saldo_usd')), 0.01);
        self::assertEqualsWithDelta($resumen['vencido_usd'], array_sum(array_column($filas, 'vencido_usd')), 0.01);
        self::assertSame(
            count(array_filter($filas, static fn (array $f): bool => (float) $f['vencido_usd'] > 0)),
            (new CobranzaRepository())->listado('vencidos', 'vencido', 1)['total'],
            'el total de "con saldo vencido" son los clientes con algo vencido'
        );
    }

    /**
     * La cobranza y el Dashboard suman la misma cartera: lo que el Dashboard llama
     * «Cartera pendiente» es el saldo abierto de esta lista, y lo vencido es todo lo que
     * no esta en el tramo «Al dia».
     */
    public function testElResumenCoincideConLaCarteraDelDashboard(): void
    {
        $cliente = $this->clienteDePrueba();
        $this->boleta($cliente, 1500.0, -3);
        $this->boleta($cliente, 600.0, 20);

        $cartera = (new IngresosYCobrosRepository())->carteraPorAntiguedad();
        $resumen = (new CobranzaRepository())->resumen();

        self::assertEqualsWithDelta(array_sum($cartera), $resumen['saldo_usd'], 0.05);
        self::assertEqualsWithDelta(array_sum($cartera) - $cartera['Al día'], $resumen['vencido_usd'], 0.05);
    }

    public function testUnAlcanceOUnOrdenDesconocidoUsaElPrimeroDeLaLista(): void
    {
        $cliente = $this->clienteDePrueba();
        $this->boleta($cliente, 100.0, -5);
        $repo = new CobranzaRepository();

        self::assertSame(
            $repo->listado('vencidos', 'vencido', 1)['total'],
            $repo->listado('inventado', 'inventado', 1)['total'],
            'un alcance desconocido cuenta como "con saldo vencido"'
        );
        self::assertSame(
            array_column($repo->listado('vencidos', 'vencido', 1)['filas'], 'cliente_id'),
            array_column($repo->listado('vencidos', 'inventado', 1)['filas'], 'cliente_id'),
            'un orden desconocido cuenta como "mayor monto vencido"'
        );
    }

    public function testOpcionPedidaSoloAceptaLoQueExiste(): void
    {
        self::assertSame('todos', CobranzaRepository::opcionPedida('todos', CobranzaRepository::ALCANCES));
        self::assertSame('saldo', CobranzaRepository::opcionPedida('saldo', CobranzaRepository::ORDENES));
        self::assertSame('', CobranzaRepository::opcionPedida('', CobranzaRepository::ALCANCES));
        self::assertSame('', CobranzaRepository::opcionPedida('inventado', CobranzaRepository::ORDENES));
        self::assertSame('', CobranzaRepository::opcionPedida('todos', CobranzaRepository::ORDENES), 'una clave de otra lista no vale');
    }
}
