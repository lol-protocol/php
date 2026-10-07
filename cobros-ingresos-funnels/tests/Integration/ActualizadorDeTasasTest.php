<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Tasas\ActualizadorDeTasas;
use App\Tasas\RespuestaDeTasas;
use App\Tasas\TasasInvalidas;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;

/**
 * Pasa las tasas de una fuente a monedas.tasa_a_usd. Cada test arranca como una
 * base que nunca las actualizo (las dos columnas de origen en NULL, que es lo que
 * deja el seed) y se deshace con la transaccion del test. Los valores de las tasas
 * de ejemplo no importan: cada test compara contra lo que leyo antes.
 */
final class ActualizadorDeTasasTest extends IntegracionTestCase
{
    private DateTimeImmutable $ahora;

    protected function setUp(): void
    {
        parent::setUp();
        Database::connection()->exec('UPDATE monedas SET tasa_actualizada_en = NULL, tasa_fuente = NULL');
        $this->ahora = new DateTimeImmutable('2026-10-07 12:00:00', new DateTimeZone('UTC'));
    }

    /** @return list<string> las monedas del catalogo, menos USD */
    private function catalogo(): array
    {
        /** @var list<string> */
        return Database::connection()->query("SELECT codigo FROM monedas WHERE codigo <> 'USD' ORDER BY codigo")->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Una fuente que trae todas las monedas del catalogo a 2 unidades por USD
     * (1 unidad = 0.5 USD), salvo lo que se pida: un valor cambia la tasa de esa
     * moneda y null la saca de la respuesta.
     *
     * @param array<string, mixed> $cambios codigo => unidades por USD
     * @param array<string, mixed> $extra campos de nivel superior de la respuesta
     */
    private function fuente(array $cambios = [], array $extra = []): RespuestaDeTasas
    {
        $rates = ['USD' => 1];
        foreach ($this->catalogo() as $codigo) {
            $rates[$codigo] = 2;
        }
        foreach ($cambios as $codigo => $valor) {
            if ($valor === null) {
                unset($rates[$codigo]);
            } else {
                $rates[$codigo] = $valor;
            }
        }

        return RespuestaDeTasas::desdeJson((string) json_encode(['base_code' => 'USD', 'rates' => $rates] + $extra));
    }

    /** @return array{tasa_a_usd: string, actualizada: ?string, tasa_fuente: ?string} */
    private function moneda(string $codigo): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tasa_a_usd, to_char(tasa_actualizada_en AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') AS actualizada, tasa_fuente
             FROM monedas WHERE codigo = :codigo"
        );
        $stmt->execute([':codigo' => $codigo]);

        /** @var array{tasa_a_usd: string, actualizada: ?string, tasa_fuente: ?string} */
        return $stmt->fetch();
    }

    private function conFechaEnLaBase(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM monedas WHERE tasa_actualizada_en IS NOT NULL')->fetchColumn();
    }

    public function testGuardaLaInversaDeLaCotizacionYDeDondeViene(): void
    {
        $eurAntes = $this->moneda('EUR')['tasa_a_usd'];
        $informe = (new ActualizadorDeTasas())->aplicar($this->fuente(['EUR' => 0.8, 'CLP' => 800]), 'fuente-de-prueba', ahora: $this->ahora);

        self::assertSame('1.25000000', $this->moneda('EUR')['tasa_a_usd'], '0.8 EUR por dolar = 1.25 dolares por euro');
        self::assertSame('0.00125000', $this->moneda('CLP')['tasa_a_usd']);
        self::assertSame('0.50000000', $this->moneda('MXN')['tasa_a_usd']);
        self::assertSame('fuente-de-prueba', $this->moneda('EUR')['tasa_fuente']);
        self::assertSame('2026-10-07 12:00:00', $this->moneda('EUR')['actualizada'], 'sin fecha en la respuesta, la de la descarga');
        self::assertCount(count($this->catalogo()), $informe['actualizadas']);
        self::assertSame(['anterior' => $eurAntes, 'nueva' => '1.25000000'], $informe['actualizadas']['EUR'], 'el informe dice de cuanto a cuanto');
        self::assertFalse($informe['simulacion']);
    }

    public function testElDolarNoSeToca(): void
    {
        (new ActualizadorDeTasas())->aplicar($this->fuente(), 'fuente-de-prueba', ahora: $this->ahora);

        self::assertSame('1.00000000', $this->moneda('USD')['tasa_a_usd']);
        self::assertNull($this->moneda('USD')['actualizada']);
        self::assertNull($this->moneda('USD')['tasa_fuente']);
    }

    public function testUsaLaFechaQueInformaLaFuente(): void
    {
        $haceDosDias = $this->ahora->modify('-2 days');

        (new ActualizadorDeTasas())->aplicar($this->fuente([], ['time_last_update_unix' => $haceDosDias->getTimestamp()]), 'fuente-de-prueba', ahora: $this->ahora);

        self::assertSame('2026-10-05 12:00:00', $this->moneda('EUR')['actualizada']);
    }

    public function testUnaFechaDeLaFuenteEnElFuturoNoPasaDeAhora(): void
    {
        $manana = $this->ahora->modify('+1 day');

        (new ActualizadorDeTasas())->aplicar($this->fuente([], ['time_last_update_unix' => $manana->getTimestamp()]), 'fuente-de-prueba', ahora: $this->ahora);

        self::assertSame('2026-10-07 12:00:00', $this->moneda('EUR')['actualizada']);
    }

    public function testNoTocaLasMonedasQueLaFuenteNoTrae(): void
    {
        $antesEur = $this->moneda('EUR');
        $antesClp = $this->moneda('CLP');

        $informe = (new ActualizadorDeTasas())->aplicar($this->fuente(['EUR' => null, 'CLP' => null]), 'fuente-de-prueba', ahora: $this->ahora);

        self::assertSame(['CLP', 'EUR'], $informe['sinDato']);
        self::assertSame($antesEur, $this->moneda('EUR'));
        self::assertSame($antesClp, $this->moneda('CLP'));
        self::assertSame('0.50000000', $this->moneda('MXN')['tasa_a_usd'], 'las demas si se actualizaron');
    }

    public function testUnaFuenteQueSoloTraePocasMonedasNoActualizaNada(): void
    {
        $unas = array_slice($this->catalogo(), 0, 10);
        $pocas = RespuestaDeTasas::desdeJson((string) json_encode(['base_code' => 'USD', 'rates' => array_fill_keys($unas, 2)]));

        try {
            (new ActualizadorDeTasas())->aplicar($pocas, 'fuente-de-prueba', ahora: $this->ahora);
            self::fail('una fuente con 10 monedas no alcanza');
        } catch (TasasInvalidas $e) {
            self::assertStringContainsString('solo trae 10 de las', $e->getMessage());
        }

        self::assertSame(0, $this->conFechaEnLaBase());
    }

    public function testUnaFuenteQueNoSeActualizaHaceDiasNoActualizaNada(): void
    {
        $vieja = $this->fuente([], ['time_last_update_unix' => $this->ahora->modify('-8 days')->getTimestamp()]);

        try {
            (new ActualizadorDeTasas())->aplicar($vieja, 'fuente-de-prueba', ahora: $this->ahora);
            self::fail('datos de hace 8 dias no sirven');
        } catch (TasasInvalidas $e) {
            self::assertStringContainsString('2026-09-29', $e->getMessage());
        }

        self::assertSame(0, $this->conFechaEnLaBase());
    }

    public function testUnaFuenteDeHaceSeisDiasSiSirve(): void
    {
        $informe = (new ActualizadorDeTasas())->aplicar(
            $this->fuente([], ['time_last_update_unix' => $this->ahora->modify('-6 days')->getTimestamp()]),
            'fuente-de-prueba',
            ahora: $this->ahora
        );

        self::assertNotEmpty($informe['actualizadas']);
    }

    /** Un valor malo no tira las demas: cero y texto los frena el lector; los que no entran en la columna, el actualizador. */
    public function testUnaTasaConValorImposibleSeRechazaYLasDemasSeActualizan(): void
    {
        $antes = $this->moneda('CLP');
        $informe = (new ActualizadorDeTasas())->aplicar(
            $this->fuente(['CLP' => 0, 'ARS' => 'abc', 'EUR' => 1e-12, 'JPY' => 1e12]),
            'fuente-de-prueba',
            ahora: $this->ahora
        );

        self::assertSame(['ARS', 'CLP', 'EUR', 'JPY'], array_keys($informe['rechazadas']));
        self::assertSame('no es mayor que cero', $informe['rechazadas']['CLP']);
        self::assertSame('queda fuera de lo que entra en la base', $informe['rechazadas']['EUR']);
        self::assertSame($antes, $this->moneda('CLP'));
        self::assertSame('0.50000000', $this->moneda('MXN')['tasa_a_usd']);
    }

    public function testUnSaltoDeMasDelCincuentaPorCientoSeFrenaSiLaTasaYaEraReal(): void
    {
        $actualizador = new ActualizadorDeTasas();
        $actualizador->aplicar($this->fuente(), 'fuente-de-prueba', ahora: $this->ahora);

        // 0.5 USD por unidad pasaria a 2 USD (+300%): una devaluacion de verdad, o un error de la fuente.
        $informe = $actualizador->aplicar($this->fuente(['ARS' => 0.5]), 'fuente-de-prueba', ahora: $this->ahora->modify('+1 day'));

        self::assertSame(['ARS' => ['anterior' => '0.50000000', 'propuesta' => '2.00000000']], $informe['sospechosas']);
        self::assertArrayNotHasKey('ARS', $informe['actualizadas']);
        self::assertSame('0.50000000', $this->moneda('ARS')['tasa_a_usd'], 'queda como estaba');
        self::assertSame('2026-10-07 12:00:00', $this->moneda('ARS')['actualizada'], 'y con la fecha que tenia');
    }

    public function testForzarAceptaElSalto(): void
    {
        $actualizador = new ActualizadorDeTasas();
        $actualizador->aplicar($this->fuente(), 'fuente-de-prueba', ahora: $this->ahora);

        $informe = $actualizador->aplicar($this->fuente(['ARS' => 0.5]), 'fuente-de-prueba', forzar: true, ahora: $this->ahora->modify('+1 day'));

        self::assertSame([], $informe['sospechosas']);
        self::assertSame('2.00000000', $this->moneda('ARS')['tasa_a_usd']);
        self::assertSame('2026-10-08 12:00:00', $this->moneda('ARS')['actualizada']);
    }

    /** El umbral es "mas de 50%": el 50% justo pasa y un poco mas, no. */
    public function testElBordeDelCincuentaPorCiento(): void
    {
        $actualizador = new ActualizadorDeTasas();
        $actualizador->aplicar($this->fuente(), 'fuente-de-prueba', ahora: $this->ahora);

        // De 0.5 USD a 0.25 (-50% justo, 4 unidades por USD) y a 0.2439 (-51.2%, 4.1 unidades por USD); y +40% (0.7).
        $informe = $actualizador->aplicar($this->fuente(['MXN' => 4, 'BRL' => 4.1, 'COP' => 2 / 1.4]), 'fuente-de-prueba', ahora: $this->ahora->modify('+1 day'));

        self::assertArrayHasKey('MXN', $informe['actualizadas']);
        self::assertArrayHasKey('COP', $informe['actualizadas']);
        self::assertSame(['BRL'], array_keys($informe['sospechosas']));
    }

    /** La primera carga: las de ejemplo pueden estar lejisimos de las reales, y no hay nada que proteger. */
    public function testLaPrimeraCargaAceptaCualquierDiferenciaConLasDeEjemplo(): void
    {
        $informe = (new ActualizadorDeTasas())->aplicar($this->fuente(['ARS' => 1000000]), 'fuente-de-prueba', ahora: $this->ahora);

        self::assertSame([], $informe['sospechosas']);
        self::assertSame('0.00000100', $this->moneda('ARS')['tasa_a_usd']);
    }

    public function testCorrerloDosVecesSeguidasConLaMismaFuenteNoFrenaNada(): void
    {
        $actualizador = new ActualizadorDeTasas();
        $primera = $actualizador->aplicar($this->fuente(['EUR' => 0.8]), 'fuente-de-prueba', ahora: $this->ahora);
        $segunda = $actualizador->aplicar($this->fuente(['EUR' => 0.8]), 'fuente-de-prueba', ahora: $this->ahora->modify('+1 day'));

        self::assertSame([], $segunda['sospechosas']);
        self::assertCount(count($primera['actualizadas']), $segunda['actualizadas']);
        self::assertSame('1.25000000', $this->moneda('EUR')['tasa_a_usd']);
        self::assertSame('2026-10-08 12:00:00', $this->moneda('EUR')['actualizada']);
    }

    public function testSimularHaceLasCuentasSinGuardarNada(): void
    {
        $antes = $this->moneda('EUR');
        $filasDeAuditoria = (int) Database::connection()->query('SELECT COUNT(*) FROM auditoria')->fetchColumn();

        $informe = (new ActualizadorDeTasas())->aplicar($this->fuente(['EUR' => 0.8]), 'fuente-de-prueba', simular: true, ahora: $this->ahora);

        self::assertTrue($informe['simulacion']);
        self::assertSame('1.25000000', $informe['actualizadas']['EUR']['nueva'], 'dice lo que haria');
        self::assertSame($antes, $this->moneda('EUR'));
        self::assertSame(0, $this->conFechaEnLaBase());
        self::assertSame($filasDeAuditoria, (int) Database::connection()->query('SELECT COUNT(*) FROM auditoria')->fetchColumn(), 'ni una entrada en la auditoria');
    }

    public function testDejaUnaEntradaEnLaAuditoria(): void
    {
        $informe = (new ActualizadorDeTasas())->aplicar($this->fuente(['EUR' => null]), 'fuente-de-prueba', ahora: $this->ahora);

        $fila = Database::connection()->query("SELECT accion, entidad, entidad_id, detalle FROM auditoria WHERE entidad = 'monedas' ORDER BY id DESC LIMIT 1")->fetch();

        self::assertIsArray($fila);
        self::assertSame('editar', $fila['accion']);
        self::assertSame(0, $fila['entidad_id']);
        self::assertStringContainsString('fuente-de-prueba', $fila['detalle']);
        self::assertStringContainsString('2026-10-07 12:00 UTC', $fila['detalle']);
        self::assertStringContainsString((string) count($informe['actualizadas']) . ' monedas', $fila['detalle']);
        self::assertStringContainsString('sin dato: 1', $fila['detalle']);
    }

    /** Todo o nada: si una moneda falla a mitad de camino, las anteriores (por orden de codigo) tampoco quedan actualizadas. */
    public function testSiAlgoFallaAMitadDeCaminoNoQuedaNadaActualizado(): void
    {
        $db = Database::connection();
        $auditadasAntes = (int) $db->query("SELECT COUNT(*) FROM auditoria WHERE entidad = 'monedas'")->fetchColumn();
        // Una restriccion solo para este test (se deshace con su transaccion): CLP no puede quedar en 0.00125.
        $db->exec('ALTER TABLE monedas ADD CONSTRAINT prueba_clp CHECK (codigo <> \'CLP\' OR tasa_a_usd <> 0.00125)');

        try {
            (new ActualizadorDeTasas())->aplicar($this->fuente(['CLP' => 800]), 'fuente-de-prueba', ahora: $this->ahora);
            self::fail('la restriccion tenia que frenar la actualizacion de CLP');
        } catch (PDOException $e) {
            self::assertStringContainsString('prueba_clp', $e->getMessage());
        }

        self::assertSame(0, $this->conFechaEnLaBase(), 'ni las monedas anteriores a CLP (ARS, BOB...) quedaron actualizadas');
        self::assertSame($auditadasAntes, (int) $db->query("SELECT COUNT(*) FROM auditoria WHERE entidad = 'monedas'")->fetchColumn(), 'tampoco la auditoria');
    }
}
