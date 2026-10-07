<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\MonedaRepository;
use PDO;

/**
 * Que tan reales son las tasas con las que se consolidan los totales en USD
 * (MonedaRepository::estadoDeLasTasas), que es lo que las pantallas le avisan al
 * usuario. Cada test arma el estado que necesita sobre monedas y se deshace con la
 * transaccion.
 */
final class EstadoDeLasTasasTest extends IntegracionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Database::connection()->exec('UPDATE monedas SET tasa_actualizada_en = NULL, tasa_fuente = NULL');
    }

    /** Todas las monedas (menos USD, que vale 1 y nunca se actualiza) con una tasa de hace $horas horas. */
    private function actualizarTodas(int $horas, string $fuente = 'fuente-de-prueba'): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE monedas SET tasa_actualizada_en = now() - make_interval(hours => :horas), tasa_fuente = :fuente WHERE codigo <> 'USD'"
        );
        $stmt->execute([':horas' => $horas, ':fuente' => $fuente]);
    }

    private function fecharMoneda(string $codigo, ?int $diasAtras): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE monedas SET tasa_actualizada_en = CASE WHEN :dias::int IS NULL THEN NULL ELSE now() - make_interval(days => :dias_2::int) END WHERE codigo = :codigo"
        );
        $stmt->execute([':dias' => $diasAtras, ':dias_2' => $diasAtras, ':codigo' => $codigo]);
    }

    /** Una moneda (que no es USD) con boletas en el seed. */
    private function monedaConMovimientos(): string
    {
        $codigo = Database::connection()->query("SELECT moneda_codigo FROM boletas WHERE moneda_codigo <> 'USD' GROUP BY moneda_codigo ORDER BY count(*) DESC LIMIT 1")->fetchColumn();
        self::assertIsString($codigo, 'el seed tiene boletas en otras monedas que USD');

        return $codigo;
    }

    /** Una moneda sin ninguna boleta ni pago. */
    private function monedaSinMovimientos(): string
    {
        $codigo = Database::connection()->query(
            "SELECT codigo FROM monedas
             WHERE codigo <> 'USD'
               AND codigo NOT IN (SELECT moneda_codigo FROM boletas UNION SELECT moneda_codigo FROM pagos)
             ORDER BY codigo LIMIT 1"
        )->fetchColumn();
        self::assertIsString($codigo);

        return $codigo;
    }

    public function testSiNuncaSeActualizaronTodasSonDeEjemplo(): void
    {
        $estado = MonedaRepository::estadoDeLasTasas();

        self::assertNull($estado['ultima']);
        self::assertNull($estado['fuente']);
        self::assertSame([], $estado['pendientes'], 'no se listan: son todas');
    }

    public function testConTodasAlDiaDiceDeCuandoSonYDeDondeVienen(): void
    {
        $this->actualizarTodas(48, 'la-vieja');
        Database::connection()->exec("UPDATE monedas SET tasa_actualizada_en = now() - interval '1 hour', tasa_fuente = 'la-reciente' WHERE codigo = 'EUR'");

        $estado = MonedaRepository::estadoDeLasTasas();

        self::assertNotNull($estado['ultima']);
        self::assertEqualsWithDelta(time() - 3600, $estado['ultima']->getTimestamp(), 120, 'la mas reciente es la de EUR, de hace una hora');
        self::assertSame('la-reciente', $estado['fuente']);
        self::assertSame([], $estado['pendientes']);
    }

    public function testUnaMonedaConMovimientosYTasaViejaEsPendiente(): void
    {
        $moneda = $this->monedaConMovimientos();
        $this->actualizarTodas(1);

        $this->fecharMoneda($moneda, MonedaRepository::DIAS_DE_VIGENCIA + 1);
        self::assertSame([$moneda], MonedaRepository::estadoDeLasTasas()['pendientes'], 'de hace 8 dias ya es vieja');

        $this->fecharMoneda($moneda, MonedaRepository::DIAS_DE_VIGENCIA - 1);
        self::assertSame([], MonedaRepository::estadoDeLasTasas()['pendientes'], 'de hace 6 dias todavia vale');
    }

    public function testUnaMonedaConMovimientosQueNuncaSeActualizoEsPendiente(): void
    {
        $moneda = $this->monedaConMovimientos();
        $this->actualizarTodas(1);

        $this->fecharMoneda($moneda, null);

        self::assertSame([$moneda], MonedaRepository::estadoDeLasTasas()['pendientes']);
    }

    public function testUnaMonedaSinMovimientosNoEsPendienteAunqueSeaDeEjemplo(): void
    {
        $this->actualizarTodas(1);

        $this->fecharMoneda($this->monedaSinMovimientos(), null);

        self::assertSame([], MonedaRepository::estadoDeLasTasas()['pendientes'], 'nadie factura en esa moneda: sus totales no se ven afectados');
    }

    public function testElDolarNuncaEsPendiente(): void
    {
        $this->actualizarTodas(1);
        Database::connection()->exec("UPDATE monedas SET tasa_actualizada_en = NULL WHERE codigo = 'USD'");

        self::assertSame([], MonedaRepository::estadoDeLasTasas()['pendientes']);
    }

    public function testLasPendientesVienenOrdenadasPorCodigo(): void
    {
        $this->actualizarTodas(1);
        $enUso = Database::connection()->query(
            "SELECT moneda_codigo FROM (SELECT moneda_codigo FROM boletas UNION SELECT moneda_codigo FROM pagos) u WHERE moneda_codigo <> 'USD' ORDER BY moneda_codigo"
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertGreaterThan(1, count($enUso), 'el seed factura en varias monedas');

        Database::connection()->exec('UPDATE monedas SET tasa_actualizada_en = NULL WHERE codigo IN (' . implode(',', array_map(static fn ($c): string => "'{$c}'", array_reverse($enUso))) . ')');

        self::assertSame($enUso, MonedaRepository::estadoDeLasTasas()['pendientes']);
    }
}
