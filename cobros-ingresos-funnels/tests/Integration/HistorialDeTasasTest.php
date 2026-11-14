<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Paginacion;
use App\Repositories\HistorialDeTasasRepository;
use App\Tasas\ActualizadorDeTasas;
use App\Tasas\RespuestaDeTasas;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * El historial de las tasas de cambio (migracion 018): un trigger sobre monedas anota una fila cada vez que
 * cambia la tasa, la fecha de su cotizacion o su fuente, y el repositorio lo lee. Cada test se deshace con la
 * transaccion; los que miran el historial de una moneda empiezan por vaciarlo para contar solo lo suyo.
 */
final class HistorialDeTasasTest extends IntegracionTestCase
{
    private function vaciarElHistorialDe(string $codigo): void
    {
        Database::connection()->prepare('DELETE FROM historial_de_tasas WHERE moneda_codigo = :c')->execute([':c' => $codigo]);
    }

    /** Una fila del historial a mano, con la cotizacion en UTC ('' = tasa de ejemplo). */
    private function anotar(string $codigo, string $tasa, string $cotizada, ?string $fuente = 'prueba'): void
    {
        Database::connection()->prepare(
            'INSERT INTO historial_de_tasas (moneda_codigo, tasa_a_usd, cotizada_en, fuente) VALUES (:c, :t, :q, :f)'
        )->execute([':c' => $codigo, ':t' => $tasa, ':q' => $cotizada === '' ? null : $cotizada . '+00', ':f' => $fuente]);
    }

    private function cuantas(string $codigo): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM historial_de_tasas WHERE moneda_codigo = :c');
        $stmt->execute([':c' => $codigo]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{tasa_a_usd: string, cotizada: ?string, fuente: ?string} la fila anotada al final */
    private function ultima(string $codigo): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tasa_a_usd, to_char(cotizada_en AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS cotizada, fuente
             FROM historial_de_tasas WHERE moneda_codigo = :c ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':c' => $codigo]);

        /** @var array{tasa_a_usd: string, cotizada: ?string, fuente: ?string} */
        return $stmt->fetch();
    }

    public function testCadaMonedaTieneAlMenosSuFilaInicial(): void
    {
        $sinFila = Database::connection()->query(
            'SELECT codigo FROM monedas m WHERE NOT EXISTS (SELECT 1 FROM historial_de_tasas h WHERE h.moneda_codigo = m.codigo) ORDER BY codigo'
        )->fetchAll(PDO::FETCH_COLUMN);

        self::assertSame([], $sinFila, 'la migracion anota la tasa que cada moneda tenia al aplicarse');
    }

    public function testUnCambioDeTasaAgregaUnaFilaConLoNuevo(): void
    {
        $antes = $this->cuantas('CLP');

        Database::connection()->exec("UPDATE monedas SET tasa_a_usd = 0.002, tasa_actualizada_en = '2026-10-07 12:00:00+00', tasa_fuente = 'fuente-a' WHERE codigo = 'CLP'");

        self::assertSame($antes + 1, $this->cuantas('CLP'));
        self::assertSame(['tasa_a_usd' => '0.00200000', 'cotizada' => '2026-10-07 12:00', 'fuente' => 'fuente-a'], $this->ultima('CLP'));
    }

    /** @return iterable<string, array{string}> */
    public static function cambiosQueSeAnotan(): iterable
    {
        yield 'solo la tasa' => ['tasa_a_usd = tasa_a_usd * 2'];
        yield 'solo la fecha de la cotizacion' => ["tasa_actualizada_en = '2026-10-08 00:00:00+00'"];
        yield 'solo la fuente' => ["tasa_fuente = 'otra-fuente'"];
    }

    #[DataProvider('cambiosQueSeAnotan')]
    public function testCambiarSoloUnaDeLasTresColumnasTambienSeAnota(string $cambio): void
    {
        Database::connection()->exec("UPDATE monedas SET tasa_actualizada_en = '2026-10-07 00:00:00+00', tasa_fuente = 'fuente-a' WHERE codigo = 'CLP'");
        $antes = $this->cuantas('CLP');

        Database::connection()->exec("UPDATE monedas SET {$cambio} WHERE codigo = 'CLP'");

        self::assertSame($antes + 1, $this->cuantas('CLP'));
    }

    public function testUnUpdateQueNoCambiaNadaNoAgregaFilas(): void
    {
        $db = Database::connection();
        $db->exec("UPDATE monedas SET tasa_actualizada_en = '2026-10-07 00:00:00+00', tasa_fuente = 'fuente-a' WHERE codigo = 'CLP'");
        $antes = (int) $db->query('SELECT COUNT(*) FROM historial_de_tasas')->fetchColumn();

        // Lo mismo, y las columnas que no son de la tasa (decimales, el reemplazo): ninguna es historial.
        $db->exec("UPDATE monedas SET tasa_a_usd = tasa_a_usd, tasa_actualizada_en = '2026-10-07 00:00:00+00', tasa_fuente = 'fuente-a' WHERE codigo = 'CLP'");
        $db->exec("UPDATE monedas SET decimales = decimales, nombre = nombre || ' ' WHERE codigo = 'CLP'");

        self::assertSame($antes, (int) $db->query('SELECT COUNT(*) FROM historial_de_tasas')->fetchColumn());
    }

    public function testUnaMonedaNuevaAnotaSuPrimeraFila(): void
    {
        Database::connection()->exec("INSERT INTO monedas (codigo, nombre, simbolo, tasa_a_usd) VALUES ('ZZZ', 'Moneda de prueba', 'Z', 0.5)");

        self::assertSame(1, $this->cuantas('ZZZ'));
        self::assertSame(['tasa_a_usd' => '0.50000000', 'cotizada' => null, 'fuente' => null], $this->ultima('ZZZ'));
    }

    public function testElActualizadorDeTasasDejaLaTasaNuevaEnElHistorial(): void
    {
        $db = Database::connection();
        $ahora = new DateTimeImmutable('2026-10-07 12:00:00', new DateTimeZone('UTC'));
        /** @var list<string> $catalogo */
        $catalogo = $db->query("SELECT codigo FROM monedas WHERE codigo <> 'USD'")->fetchAll(PDO::FETCH_COLUMN);
        $rates = ['USD' => 1];
        foreach ($catalogo as $codigo) {
            $rates[$codigo] = 2;
        }
        $rates['CLP'] = 800;
        $respuesta = RespuestaDeTasas::desdeJson((string) json_encode(['base_code' => 'USD', 'rates' => $rates]));
        $antes = $this->cuantas('CLP');

        (new ActualizadorDeTasas())->aplicar($respuesta, 'fuente-de-prueba', simular: true, ahora: $ahora);
        self::assertSame($antes, $this->cuantas('CLP'), 'una simulacion no escribe, tampoco en el historial');

        (new ActualizadorDeTasas())->aplicar($respuesta, 'fuente-de-prueba', ahora: $ahora);
        self::assertSame($antes + 1, $this->cuantas('CLP'));
        self::assertSame(['tasa_a_usd' => '0.00125000', 'cotizada' => '2026-10-07 12:00', 'fuente' => 'fuente-de-prueba'], $this->ultima('CLP'));
    }

    public function testElHistorialVaDeLaMasRecienteALaMasAntiguaYDiceElCambioFrenteALaAnterior(): void
    {
        $this->vaciarElHistorialDe('CLP');
        $this->anotar('CLP', '0.00100000', '', null);
        $this->anotar('CLP', '0.00100000', '2026-10-01 12:00');
        $this->anotar('CLP', '0.00110000', '2026-10-02 12:00');
        $this->anotar('CLP', '0.00099000', '2026-10-03 12:00');

        $pagina = (new HistorialDeTasasRepository())->deLaMoneda('CLP', 1);

        self::assertSame(4, $pagina['total']);
        $tasas = array_map(static fn (array $f): string => $f['tasa_a_usd'], $pagina['filas']);
        self::assertSame(['0.00099000', '0.00110000', '0.00100000', '0.00100000'], $tasas, 'de la mas reciente a la mas antigua');
        $anteriores = array_map(static fn (array $f): ?string => $f['tasa_anterior'], $pagina['filas']);
        self::assertSame(['0.00110000', '0.00100000', null, null], $anteriores, 'frente a la fila anterior; una real contra una de ejemplo no se compara, ni la primera del historial');
    }

    public function testElHistorialDeUnaMonedaNoTraeLasFilasDeOtra(): void
    {
        $this->vaciarElHistorialDe('CLP');
        $this->vaciarElHistorialDe('EUR');
        $this->anotar('CLP', '0.00100000', '2026-10-01 12:00');
        $this->anotar('EUR', '1.10000000', '2026-10-01 12:00');
        $this->anotar('CLP', '0.00120000', '2026-10-02 12:00');

        $pagina = (new HistorialDeTasasRepository())->deLaMoneda('CLP', 1);

        self::assertSame(2, $pagina['total']);
        self::assertSame('0.00100000', $pagina['filas'][0]['tasa_anterior'], 'la fila anterior es de la misma moneda, no la del euro que se anoto en medio');
        self::assertNull($pagina['filas'][1]['tasa_anterior'], 'la primera de la moneda no tiene anterior, aunque haya filas de otras antes');
    }

    public function testElHistorialSePaginaYUnaPaginaPasadaDelFinalSeAcota(): void
    {
        $this->vaciarElHistorialDe('CLP');
        Database::connection()->exec(
            "INSERT INTO historial_de_tasas (moneda_codigo, tasa_a_usd, cotizada_en, fuente)
             SELECT 'CLP', 0.001 + n * 0.000001, timestamptz '2026-01-01 00:00+00' + n * interval '1 day', 'prueba' FROM generate_series(1, 60) n"
        );
        $repo = new HistorialDeTasasRepository();

        $primera = $repo->deLaMoneda('CLP', 1);
        self::assertSame(60, $primera['total']);
        self::assertSame(3, $primera['totalPaginas']);
        self::assertCount(Paginacion::POR_PAGINA, $primera['filas']);

        $ultima = $repo->deLaMoneda('CLP', 99999);
        self::assertSame(3, $ultima['pagina']);
        self::assertCount(60 - 2 * Paginacion::POR_PAGINA, $ultima['filas']);
        self::assertSame('0.00100100', $ultima['filas'][count($ultima['filas']) - 1]['tasa_a_usd'], 'la ultima fila de la ultima pagina es la mas antigua');
    }

    public function testLaExportacionTraeTodasLasFilasEnElMismoOrdenQueLasPaginas(): void
    {
        $this->vaciarElHistorialDe('CLP');
        $this->vaciarElHistorialDe('EUR');
        Database::connection()->exec(
            "INSERT INTO historial_de_tasas (moneda_codigo, tasa_a_usd, cotizada_en, fuente)
             SELECT 'CLP', 0.001 + n * 0.000001, timestamptz '2026-01-01 00:00+00' + n * interval '1 day', 'prueba' FROM generate_series(1, 60) n"
        );
        $this->anotar('EUR', '1.10000000', '2026-10-01 12:00');
        $repo = new HistorialDeTasasRepository();

        $porPaginas = [];
        for ($pagina = 1; $pagina <= 3; $pagina++) {
            foreach ($repo->deLaMoneda('CLP', $pagina)['filas'] as $fila) {
                $porPaginas[] = [$fila['id'], $fila['tasa_a_usd'], $fila['tasa_anterior']];
            }
        }
        $exportadas = [];
        foreach ($repo->exportarHistorial('CLP') as $fila) {
            $exportadas[] = [$fila['id'], $fila['tasa_a_usd'], $fila['tasa_anterior']];
        }

        self::assertCount(60, $exportadas, 'todas las filas de la moneda, mas alla de la primera pagina, y ninguna de otra');
        self::assertSame($porPaginas, $exportadas, 'las mismas filas, en el mismo orden y con el mismo cambio frente a la anterior');
        self::assertSame('0.00106000', $exportadas[0][1], 'de la mas reciente a la mas antigua');
    }

    public function testElResumenComparaConLaTasaDeHaceTreintaDiasDeSuUltimaCotizacion(): void
    {
        $db = Database::connection();
        // CLP: cotizada el 7 de octubre. Hace 30 dias es el 7 de septiembre a las 12:00.
        $db->exec("UPDATE monedas SET tasa_a_usd = 0.002, tasa_actualizada_en = '2026-10-07 12:00:00+00' WHERE codigo = 'CLP'");
        $this->vaciarElHistorialDe('CLP');
        $this->anotar('CLP', '0.00100000', '2026-09-01 00:00');
        $this->anotar('CLP', '0.00120000', '2026-09-07 12:00'); // justo 30 dias antes: cuenta
        $this->anotar('CLP', '0.00130000', '2026-09-07 12:01'); // un minuto despues: no
        $this->anotar('CLP', '0.00200000', '2026-10-07 12:00');
        // EUR: su historial es mas corto que 30 dias, no hay con que comparar.
        $db->exec("UPDATE monedas SET tasa_a_usd = 1.1, tasa_actualizada_en = '2026-10-07 12:00:00+00' WHERE codigo = 'EUR'");
        $this->vaciarElHistorialDe('EUR');
        $this->anotar('EUR', '1.05000000', '2026-10-01 00:00');
        // MXN: tasa de ejemplo; aunque tenga historial, no se compara.
        $db->exec('UPDATE monedas SET tasa_actualizada_en = NULL WHERE codigo = \'MXN\'');
        $this->anotar('MXN', '0.05000000', '2026-09-01 00:00');

        $porMoneda = [];
        foreach ((new HistorialDeTasasRepository())->resumen() as $fila) {
            $porMoneda[$fila['codigo']] = $fila;
        }

        self::assertSame('0.00120000', $porMoneda['CLP']['tasa_antes'], 'la mas reciente de las que ya existian 30 dias antes, con el limite incluido');
        self::assertSame('0.00200000', $porMoneda['CLP']['tasa_a_usd']);
        self::assertNull($porMoneda['EUR']['tasa_antes'], 'sin historial tan largo no se inventa una comparacion');
        self::assertNull($porMoneda['MXN']['tasa_antes'], 'una tasa de ejemplo no se compara');
        self::assertNull($porMoneda['MXN']['cotizada_en']);
        self::assertArrayNotHasKey('USD', $porMoneda, 'el dolar es la base: no tiene tasa que seguir');
    }

    public function testLasMonedasParaElegirNoIncluyenElDolar(): void
    {
        $monedas = (new HistorialDeTasasRepository())->monedas();

        self::assertArrayNotHasKey('USD', $monedas);
        self::assertArrayHasKey('CLP', $monedas);
        self::assertStringStartsWith('CLP — ', $monedas['CLP']);
    }
}
