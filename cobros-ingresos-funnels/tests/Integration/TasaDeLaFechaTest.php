<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\NotaCreditoRepository;
use App\Repositories\PagoRepository;
use DateTimeImmutable;

/**
 * Migracion 020: una fila nueva toma la tasa de su fecha (la ultima cotizacion del historial de ese dia o antes);
 * sin cotizacion anterior, o con fecha de hoy o futura, la tasa de hoy. Usa una moneda sin movimientos y un historial
 * armado a mano (se borra el de esa moneda dentro de la transaccion del test, que se deshace).
 */
final class TasaDeLaFechaTest extends IntegracionTestCase
{
    private string $moneda;
    private int $clienteId;
    private DateTimeImmutable $hoy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoy = new DateTimeImmutable('today');
        $moneda = Database::connection()->query(
            "SELECT m.codigo FROM monedas m
             WHERE m.codigo <> 'USD'
               AND NOT EXISTS (SELECT 1 FROM boletas b WHERE b.moneda_codigo = m.codigo)
               AND NOT EXISTS (SELECT 1 FROM pagos p WHERE p.moneda_codigo = m.codigo)
               AND NOT EXISTS (SELECT 1 FROM notas_credito n WHERE n.moneda_codigo = m.codigo)
             ORDER BY m.codigo LIMIT 1"
        )->fetchColumn();
        self::assertIsString($moneda);
        $this->moneda = $moneda;

        Database::connection()->prepare('DELETE FROM historial_de_tasas WHERE moneda_codigo = :m')->execute([':m' => $moneda]);
        Database::connection()->prepare('UPDATE monedas SET tasa_a_usd = 0.5 WHERE codigo = :m')->execute([':m' => $moneda]);
        Database::connection()->prepare('DELETE FROM historial_de_tasas WHERE moneda_codigo = :m')->execute([':m' => $moneda]);  // el UPDATE anoto una fila

        $this->clienteId = (new ClienteRepository())->crear([
            'nombre' => 'Cliente de la tasa de la fecha', 'email' => 'fecha-' . uniqid('', true) . '@example.com',
            'segmento' => 'general', 'fecha_alta' => $this->hoy->format('Y-m-d'), 'pais_codigo' => 'AR',
            'ciudad' => 'Ciudad de la tasa', 'idioma' => 'Espanol', 'genero' => 'No especifica', 'fecha_nacimiento' => '1990-05-05',
        ]);
    }

    /** Una cotizacion del historial, hecha el $dias dias antes de hoy, a medio dia UTC. */
    private function cotizacion(int $dias, string $tasa, bool $deEjemplo = false): void
    {
        $cuando = $deEjemplo ? null : $this->hoy->modify("-{$dias} days")->format('Y-m-d') . ' 12:00:00+00';
        Database::connection()->prepare(
            'INSERT INTO historial_de_tasas (moneda_codigo, tasa_a_usd, cotizada_en, fuente) VALUES (:m, :t, :c, \'prueba\')'
        )->execute([':m' => $this->moneda, ':t' => $tasa, ':c' => $cuando]);
    }

    private function fecha(int $diasAtras): string
    {
        return $this->hoy->modify("-{$diasAtras} days")->format('Y-m-d');
    }

    private function boleta(string $emision): int
    {
        return (new BoletaRepository())->crear([
            'cliente_id' => $this->clienteId, 'concepto' => 'Boleta de la tasa', 'monto' => 100, 'moneda_codigo' => $this->moneda,
            'fecha_emision' => $emision, 'fecha_vencimiento' => $this->hoy->modify('+30 days')->format('Y-m-d'),
        ]);
    }

    private function tasaDe(string $tabla, int $id): string
    {
        $s = Database::connection()->prepare("SELECT tasa_a_usd FROM {$tabla} WHERE id = :id");
        $s->execute([':id' => $id]);

        return rtrim(rtrim((string) $s->fetchColumn(), '0'), '.');
    }

    public function testUnaBoletaDeHaceUnMesTomaLaCotizacionDeEseDia(): void
    {
        $this->cotizacion(60, '0.40');
        $this->cotizacion(40, '0.45');   // la ultima hecha el dia 35 o antes
        $this->cotizacion(10, '0.48');

        self::assertSame('0.45', $this->tasaDe('boletas', $this->boleta($this->fecha(35))));
        self::assertSame('0.4', $this->tasaDe('boletas', $this->boleta($this->fecha(50))));
        self::assertSame('0.48', $this->tasaDe('boletas', $this->boleta($this->fecha(10))), 'el mismo dia de la cotizacion cuenta');
    }

    public function testElDiaAnteriorALaCotizacionNoLaUsa(): void
    {
        $this->cotizacion(40, '0.45');

        self::assertSame('0.5', $this->tasaDe('boletas', $this->boleta($this->fecha(41))), 'sin cotizacion anterior: la de hoy');
    }

    public function testHoyYElFuturoUsanLaTasaDeHoy(): void
    {
        $this->cotizacion(5, '0.48');
        Database::connection()->prepare('UPDATE monedas SET tasa_a_usd = 0.55 WHERE codigo = :m')->execute([':m' => $this->moneda]);

        self::assertSame('0.55', $this->tasaDe('boletas', $this->boleta($this->fecha(0))));
        self::assertSame('0.55', $this->tasaDe('boletas', $this->boleta($this->hoy->modify('+20 days')->format('Y-m-d'))));
    }

    public function testUnaCotizacionDeEjemploNoCuenta(): void
    {
        $this->cotizacion(0, '0.99', true);

        self::assertSame('0.5', $this->tasaDe('boletas', $this->boleta($this->fecha(30))));
    }

    public function testSinHistorialQuedaLaTasaDeHoy(): void
    {
        self::assertSame('0.5', $this->tasaDe('boletas', $this->boleta($this->fecha(200))));
    }

    public function testUnPagoTomaLaTasaDeSuFechaDePagoNoLaDeLaBoleta(): void
    {
        $this->cotizacion(60, '0.40');
        $this->cotizacion(20, '0.46');
        $boleta = $this->boleta($this->fecha(50));
        $pago = (new PagoRepository())->crear([
            'boleta_id' => $boleta, 'cliente_id' => $this->clienteId, 'monto' => 50, 'moneda_codigo' => $this->moneda,
            'fecha_pago' => $this->fecha(15), 'metodo' => 'transferencia',
        ]);

        self::assertSame('0.4', $this->tasaDe('boletas', $boleta));
        self::assertSame('0.46', $this->tasaDe('pagos', $pago));
    }

    public function testLaCotizacionDeOtraMonedaNoSeCuela(): void
    {
        $this->cotizacion(60, '0.40');
        $otra = Database::connection()->prepare("SELECT codigo FROM monedas WHERE codigo NOT IN ('USD', :m) ORDER BY codigo LIMIT 1");
        $otra->execute([':m' => $this->moneda]);
        Database::connection()->prepare(
            'INSERT INTO historial_de_tasas (moneda_codigo, tasa_a_usd, cotizada_en, fuente) VALUES (:m, 7, :c, \'prueba\')'
        )->execute([':m' => $otra->fetchColumn(), ':c' => $this->fecha(20) . ' 12:00:00+00']);

        self::assertSame('0.4', $this->tasaDe('boletas', $this->boleta($this->fecha(10))), 'la ultima de ESTA moneda, no la mas reciente de otra');
    }

    public function testUnaTasaExplicitaSeRespeta(): void
    {
        $this->cotizacion(60, '0.40');
        Database::connection()->prepare(
            'INSERT INTO boletas (cliente_id, concepto, monto, moneda_codigo, fecha_emision, fecha_vencimiento, tasa_a_usd)
             VALUES (:c, \'x\', 10, :m, :e, :v, 0.123)'
        )->execute([':c' => $this->clienteId, ':m' => $this->moneda, ':e' => $this->fecha(30), ':v' => $this->hoy->format('Y-m-d')]);
        $id = (int) Database::connection()->lastInsertId('boletas_id_seq');

        self::assertSame('0.123', $this->tasaDe('boletas', $id));
    }

    public function testEditarUnaBoletaNoLaVuelveACotizar(): void
    {
        $this->cotizacion(60, '0.40');
        $id = $this->boleta($this->fecha(30));
        $antes = $this->tasaDe('boletas', $id);
        $this->cotizacion(20, '0.47');

        Database::connection()->prepare('UPDATE boletas SET fecha_emision = :f WHERE id = :id')->execute([':f' => $this->fecha(10), ':id' => $id]);

        self::assertSame($antes, $this->tasaDe('boletas', $id));
    }

    public function testUnaNotaDeCreditoSinPagosTomaLaTasaDeSuFecha(): void
    {
        $this->cotizacion(60, '0.40');
        $this->cotizacion(20, '0.46');
        $boleta = $this->boleta($this->fecha(50));
        $nota = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta, 'cliente_id' => $this->clienteId, 'monto' => 30, 'moneda_codigo' => $this->moneda,
            'motivo' => 'ajuste', 'fecha' => $this->fecha(25),
        ]);

        self::assertSame('0.4', $this->tasaDe('notas_credito', $nota));
    }
}
