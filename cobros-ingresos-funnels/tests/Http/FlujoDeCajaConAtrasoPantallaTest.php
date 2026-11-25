<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\PagoRepository;
use DateTimeImmutable;

/**
 * El CSV y la pantalla del flujo de caja con datos que mueven el atraso habitual: un cliente con tres boletas pagadas
 * 20 dias tarde y una abierta que vence el ultimo dia de este mes. Con el supuesto del vencimiento esos 100 USD caen
 * este mes; con el del atraso, el que viene. El servidor commitea: lo que se crea se borra al terminar.
 */
final class FlujoDeCajaConAtrasoPantallaTest extends HttpTestCase
{
    private function clienteConAtrasoYUnaBoletaAbierta(): void
    {
        $hoy = new DateTimeImmutable('today');
        $marca = strtolower(uniqid('atr'));
        $cliente = (new ClienteRepository())->crear([
            'nombre' => 'Cliente ' . $marca, 'email' => $marca . '@example.com', 'segmento' => 'general',
            'fecha_alta' => $hoy->format('Y-m-d'), 'pais_codigo' => 'US', 'ciudad' => 'Miami', 'idioma' => 'Ingles',
            'genero' => 'No especifica', 'fecha_nacimiento' => '1980-01-01',
        ]);
        $this->alTerminar(static function () use ($cliente): void {
            $db = Database::connection();
            foreach (['pagos', 'boletas'] as $tabla) {
                $db->prepare("DELETE FROM {$tabla} WHERE cliente_id = :id")->execute([':id' => $cliente]);
            }
            $db->prepare('DELETE FROM clientes WHERE id = :id')->execute([':id' => $cliente]);
        });

        $vence = $hoy->modify('-200 days');
        for ($i = 0; $i < 3; $i++) {
            $boleta = (new BoletaRepository())->crear([
                'cliente_id' => $cliente, 'concepto' => 'Historial', 'monto' => 100, 'moneda_codigo' => 'USD',
                'fecha_emision' => '2020-01-01', 'fecha_vencimiento' => $vence->format('Y-m-d'),
            ]);
            (new PagoRepository())->crear([
                'boleta_id' => $boleta, 'cliente_id' => $cliente, 'monto' => 100, 'moneda_codigo' => 'USD',
                'fecha_pago' => $vence->modify('+20 days')->format('Y-m-d'), 'metodo' => 'transferencia',
            ]);
        }
        (new BoletaRepository())->crear([
            'cliente_id' => $cliente, 'concepto' => 'Abierta', 'monto' => 100, 'moneda_codigo' => 'USD',
            'fecha_emision' => '2020-01-01', 'fecha_vencimiento' => (new DateTimeImmutable('last day of this month'))->format('Y-m-d'),
        ]);
    }

    /** @return array<string, float> el saldo de cada fila del CSV, por su primera columna */
    private function saldosDelCsv(string $query): array
    {
        $saldos = [];
        foreach (array_slice(self::leer($this->get($query)['cuerpo']), 1) as $fila) {
            $saldos[(string) $fila[0]] = (float) $fila[1];
        }

        return $saldos;
    }

    public function testElCsvRespetaElSupuestoYLoMovidoCaeEnElMesQueViene(): void
    {
        $antesVencimiento = $this->saldosDelCsv('page=flujo-csv&supuesto=vencimiento');
        $antesAtraso = $this->saldosDelCsv('page=flujo-csv&supuesto=atraso');
        $this->clienteConAtrasoYUnaBoletaAbierta();
        $esteMes = (new DateTimeImmutable('today'))->format('Y-m');
        $mesQueViene = (new DateTimeImmutable('first day of next month'))->format('Y-m');

        $vencimiento = $this->saldosDelCsv('page=flujo-csv&supuesto=vencimiento');
        $atraso = $this->saldosDelCsv('page=flujo-csv&supuesto=atraso');
        $porDefecto = $this->saldosDelCsv('page=flujo-csv');

        self::assertEqualsWithDelta(100.0, $vencimiento[$esteMes] - $antesVencimiento[$esteMes], 0.001, 'vencimiento: este mes');
        self::assertEqualsWithDelta(0.0, $vencimiento[$mesQueViene] - $antesVencimiento[$mesQueViene], 0.001);
        self::assertEqualsWithDelta(0.0, $atraso[$esteMes] - $antesAtraso[$esteMes], 0.001, 'atraso: ya no cae este mes');
        self::assertEqualsWithDelta(100.0, $atraso[$mesQueViene] - $antesAtraso[$mesQueViene], 0.001, 'atraso: el mes que viene');
        self::assertSame($atraso, $porDefecto, 'sin pedir supuesto, el CSV usa el de por defecto, el mismo que la pantalla');
    }

    /** El saldo que la pantalla muestra en la fila de un mes (su etiqueta corta, «nov 2026»). */
    private function saldoEnPantalla(string $query, string $mes): float
    {
        $cuerpo = $this->get($query)['cuerpo'];
        if (preg_match('~<td>' . preg_quote(etiqueta_de_mes($mes), '~') . '</td>\s*<td class="num">\$([\d,\.]+)</td>~', $cuerpo, $m) !== 1) {
            self::fail("la pantalla no tiene la fila de {$mes}");
        }

        return (float) str_replace(',', '', $m[1]);
    }

    public function testLaPantallaMuestraLasCifrasDelSupuestoPedido(): void
    {
        $esteMes = (new DateTimeImmutable('today'))->format('Y-m');
        $mesQueViene = (new DateTimeImmutable('first day of next month'))->format('Y-m');
        $antes = [
            'vencimiento' => [$this->saldoEnPantalla('page=flujo&supuesto=vencimiento', $esteMes), $this->saldoEnPantalla('page=flujo&supuesto=vencimiento', $mesQueViene)],
            'atraso' => [$this->saldoEnPantalla('page=flujo&supuesto=atraso', $esteMes), $this->saldoEnPantalla('page=flujo&supuesto=atraso', $mesQueViene)],
        ];
        $this->clienteConAtrasoYUnaBoletaAbierta();

        $vencimiento = [$this->saldoEnPantalla('page=flujo&supuesto=vencimiento', $esteMes), $this->saldoEnPantalla('page=flujo&supuesto=vencimiento', $mesQueViene)];
        $atraso = [$this->saldoEnPantalla('page=flujo&supuesto=atraso', $esteMes), $this->saldoEnPantalla('page=flujo&supuesto=atraso', $mesQueViene)];
        $porDefecto = [$this->saldoEnPantalla('page=flujo', $esteMes), $this->saldoEnPantalla('page=flujo', $mesQueViene)];

        self::assertEqualsWithDelta(100.0, $vencimiento[0] - $antes['vencimiento'][0], 0.01, 'vencimiento: los 100 caen este mes');
        self::assertEqualsWithDelta(0.0, $vencimiento[1] - $antes['vencimiento'][1], 0.01);
        self::assertEqualsWithDelta(0.0, $atraso[0] - $antes['atraso'][0], 0.01, 'atraso: ya no caen este mes');
        self::assertEqualsWithDelta(100.0, $atraso[1] - $antes['atraso'][1], 0.01, 'atraso: caen el mes que viene');
        self::assertSame($atraso, $porDefecto, 'sin pedir supuesto, la pantalla usa el del atraso');
    }

    public function testLaPantallaDiceCuantoSeMovioPorElAtraso(): void
    {
        $this->clienteConAtrasoYUnaBoletaAbierta();

        $cuerpo = $this->get('page=flujo&supuesto=atraso')['cuerpo'];

        self::assertMatchesRegularExpression('~\$100\.00 \(\d+&nbsp;%\) son de clientes con historial de pagos y se movieron según su atraso habitual~', $cuerpo);
    }
}
