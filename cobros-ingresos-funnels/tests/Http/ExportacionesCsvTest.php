<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\PagoRepository;
use DateTimeImmutable;

/**
 * Las descargas CSV de Cobros, Pagos y Cobranza contra la app levantada. Los datos que
 * crean (el servidor commitea) se borran al terminar.
 */
final class ExportacionesCsvTest extends HttpTestCase
{
    private const PAIS = 'US';
    private const AMPLIO = 'desde=2000-01-01&hasta=2100-12-31';

    /**
     * Un cliente de prueba con un nombre que una planilla tomaria por formula, y una marca
     * unica para filtrar solo sus filas. Se borra todo lo suyo al terminar.
     *
     * @return array{id: int, marca: string, nombre: string}
     */
    private function clientePeligroso(): array
    {
        $marca = 'MARCA' . strtoupper(uniqid());
        $nombre = '=HYPERLINK("http://evil.example","clic") ' . $marca;
        $id = (new ClienteRepository())->crear([
            'nombre' => $nombre, 'email' => strtolower($marca) . '@example.com', 'segmento' => 'general',
            'fecha_alta' => '2020-01-01', 'pais_codigo' => self::PAIS, 'ciudad' => 'Miami', 'idioma' => 'Ingles',
            'genero' => 'No especifica', 'fecha_nacimiento' => '1990-05-05',
        ]);
        $this->alTerminar(static function () use ($id): void {
            $db = Database::connection();
            foreach (['pagos', 'boletas'] as $tabla) {
                $db->prepare("DELETE FROM {$tabla} WHERE cliente_id = :id")->execute([':id' => $id]);
            }
            $db->prepare('DELETE FROM clientes WHERE id = :id')->execute([':id' => $id]);
        });

        return ['id' => $id, 'marca' => $marca, 'nombre' => $nombre];
    }

    private static function moneda(): string
    {
        return (string) Database::connection()->query("SELECT moneda_codigo FROM paises WHERE codigo = '" . self::PAIS . "'")->fetchColumn();
    }

    /** Una boleta del cliente de 1000, emitida en 2020 y vencida; con $concepto como texto libre. */
    private function boleta(int $clienteId, string $concepto, float $monto = 1000.0): int
    {
        return (new BoletaRepository())->crear([
            'cliente_id' => $clienteId, 'concepto' => $concepto, 'monto' => $monto, 'moneda_codigo' => self::moneda(),
            'fecha_emision' => '2020-01-01', 'fecha_vencimiento' => '2020-02-01',
        ]);
    }

    private function pago(int $clienteId, ?int $boletaId, float $monto): int
    {
        return (new PagoRepository())->crear([
            'boleta_id' => $boletaId, 'cliente_id' => $clienteId, 'monto' => $monto, 'moneda_codigo' => self::moneda(),
            'fecha_pago' => '2020-01-15', 'metodo' => 'transferencia',
        ]);
    }

    /**
     * Las filas de un CSV, ya leidas (la primera es el encabezado). Comprueba de paso la
     * marca de orden UTF-8 del principio y la saca.
     *
     * @return list<list<string|null>>
     */
    private static function leer(string $cuerpo): array
    {
        self::assertStringStartsWith("\xEF\xBB\xBF", $cuerpo, 'el archivo empieza con la marca UTF-8');
        $flujo = fopen('php://memory', 'w+');
        self::assertNotFalse($flujo);
        fwrite($flujo, substr($cuerpo, 3));
        rewind($flujo);
        $filas = [];
        while (($fila = fgetcsv($flujo, null, ',', '"', '')) !== false) {
            $filas[] = $fila;
        }

        return $filas;
    }

    public function testLaDescargaEsUnCsvAdjuntoSinCacheConSuEncabezado(): void
    {
        $cliente = $this->clientePeligroso();
        $this->boleta($cliente['id'], 'Boleta de la descarga');

        $respuesta = $this->get('page=cobros-csv&' . self::AMPLIO . '&cliente=' . $cliente['marca']);

        $this->assertStatus(200, $respuesta);
        self::assertMatchesRegularExpression('/^Content-Type: text\/csv; charset=utf-8\r?$/mi', $respuesta['cabeceras']);
        self::assertMatchesRegularExpression('/^Content-Disposition: attachment; filename="cobros-' . date('Y-m-d') . '\.csv"\r?$/mi', $respuesta['cabeceras']);
        self::assertMatchesRegularExpression('/^Cache-Control: no-store\r?$/mi', $respuesta['cabeceras'], 'tiene datos personales: no se cachea');
        self::assertSame(
            ['ID', 'Cliente ID', 'Cliente', 'Concepto', 'Moneda', 'Monto', 'Pagado', 'Saldo', 'Emisión', 'Vencimiento', 'Estado'],
            self::leer($respuesta['cuerpo'])[0]
        );
    }

    public function testLasCeldasQueUnaPlanillaTomariaPorFormulaSalenNeutralizadas(): void
    {
        $cliente = $this->clientePeligroso();
        $this->boleta($cliente['id'], '+SUM(1+1)');

        $filas = self::leer($this->get('page=cobros-csv&' . self::AMPLIO . '&cliente=' . $cliente['marca'])['cuerpo']);

        self::assertCount(2, $filas, 'el encabezado y la unica boleta del cliente');
        [, , $nombre, $concepto, $moneda, $monto, $pagado, $saldo, $emision, $vencimiento, $estado] = $filas[1];
        self::assertSame("'" . $cliente['nombre'], $nombre, 'el nombre que empieza con = lleva un apostrofe');
        self::assertSame("'+SUM(1+1)", $concepto, 'y el concepto que empieza con +');
        self::assertSame(['1000.00', '0.00', '1000.00'], [$monto, $pagado, $saldo], 'los montos siguen siendo numeros');
        self::assertSame(['2020-01-01', '2020-02-01', 'Vencida'], [$emision, $vencimiento, $estado]);
        self::assertSame(self::moneda(), $moneda);
    }

    public function testExportaTodasLasFilasDelFiltroAunqueLaPantallaLasPagine(): void
    {
        $cliente = $this->clientePeligroso();
        for ($i = 1; $i <= 30; $i++) {
            $this->boleta($cliente['id'], "Boleta {$i}");
        }

        $pantalla = $this->get('page=cobros&' . self::AMPLIO . '&cliente=' . $cliente['marca']);
        $csv = self::leer($this->get('page=cobros-csv&' . self::AMPLIO . '&cliente=' . $cliente['marca'])['cuerpo']);

        self::assertStringContainsString('Boletas (30)', $pantalla['cuerpo']);
        self::assertCount(31, $csv, 'las 30 boletas (la pantalla muestra 25 por pagina) mas el encabezado');
    }

    public function testElEstadoYElClienteDeLaUrlSeRespetan(): void
    {
        $cliente = $this->clientePeligroso();
        $pagada = $this->boleta($cliente['id'], 'Esta esta pagada');
        $this->pago($cliente['id'], $pagada, 1000.0);
        $this->boleta($cliente['id'], 'Esta esta vencida');
        $query = 'page=cobros-csv&' . self::AMPLIO . '&cliente=' . $cliente['marca'];
        $conceptos = static fn (array $filas): array => array_column(array_slice($filas, 1), 3);

        self::assertEqualsCanonicalizing(['Esta esta pagada', 'Esta esta vencida'], $conceptos(self::leer($this->get($query)['cuerpo'])));
        self::assertSame(['Esta esta pagada'], $conceptos(self::leer($this->get($query . '&estado=pagada')['cuerpo'])));
        self::assertSame(['Esta esta vencida'], $conceptos(self::leer($this->get($query . '&estado=vencida')['cuerpo'])));
        self::assertSame([], $conceptos(self::leer($this->get('page=cobros-csv&' . self::AMPLIO . '&cliente=NOEXISTE' . uniqid())['cuerpo'])), 'otro cliente: ninguna fila');
    }

    public function testUnFiltroInvalidoNoRompeLaDescargaYCaeEnElPorDefecto(): void
    {
        $this->assertStatus(200, $this->get('page=cobros-csv&estado=inventado&meses=7'));
        $this->assertStatus(200, $this->get('page=cobros-csv&estado[]=x&desde=basura&hasta=2026-13-45'));
        $this->assertStatus(200, $this->get('page=pagos-csv&meses=7&pagina=9999999999999999999'));
        $this->assertStatus(200, $this->get('page=cobranza-csv&alcance=inventado&orden[]=saldo'));
    }

    public function testLosPagosSeExportanConSuBoletaOComoAnticipo(): void
    {
        $cliente = $this->clientePeligroso();
        $boleta = $this->boleta($cliente['id'], 'Para pagar');
        $conBoleta = $this->pago($cliente['id'], $boleta, 250.5);
        $anticipo = $this->pago($cliente['id'], null, 99.0);
        (new PagoRepository())->anularSiEstabaActiva($anticipo);

        $respuesta = $this->get('page=pagos-csv&' . self::AMPLIO . '&cliente=' . $cliente['marca']);

        $this->assertStatus(200, $respuesta);
        self::assertMatchesRegularExpression('/filename="pagos-' . date('Y-m-d') . '\.csv"/', $respuesta['cabeceras']);
        $filas = self::leer($respuesta['cuerpo']);
        self::assertSame(['ID', 'Fecha', 'Cliente ID', 'Cliente', 'Boleta', 'Método', 'Moneda', 'Monto', 'Anulado'], $filas[0]);
        $porId = array_column(array_slice($filas, 1), null, 0);
        self::assertCount(2, $porId);
        self::assertSame(
            [(string) $conBoleta, '2020-01-15', (string) $cliente['id'], "'" . $cliente['nombre'], (string) $boleta, 'Transferencia', self::moneda(), '250.50', 'No'],
            $porId[(string) $conBoleta]
        );
        self::assertSame('', $porId[(string) $anticipo][4], 'un anticipo no tiene boleta');
        self::assertSame('Sí', $porId[(string) $anticipo][8], 'y este esta anulado');
    }

    public function testLaCobranzaSeExportaConLosDiasYLosMontosComoNumeros(): void
    {
        $cliente = $this->clientePeligroso();
        $this->boleta($cliente['id'], 'Debe', 1000.0);
        $dias = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable('2020-02-01'))->days;

        $respuesta = $this->get('page=cobranza-csv&alcance=todos');

        $this->assertStatus(200, $respuesta);
        self::assertMatchesRegularExpression('/filename="cobranza-' . date('Y-m-d') . '\.csv"/', $respuesta['cabeceras']);
        $filas = self::leer($respuesta['cuerpo']);
        self::assertSame(
            ['Cliente ID', 'Cliente', 'Email', 'País', 'Ciudad', 'Boletas', 'Saldo USD', 'Vencido USD', 'Días de la boleta más vencida'],
            $filas[0]
        );
        $suyas = array_values(array_filter(array_slice($filas, 1), static fn (array $f): bool => $f[0] === (string) $cliente['id']));
        self::assertCount(1, $suyas);
        self::assertSame("'" . $cliente['nombre'], $suyas[0][1]);
        self::assertSame(strtolower($cliente['marca']) . '@example.com', $suyas[0][2]);
        self::assertSame('1', $suyas[0][5]);
        self::assertSame((string) $dias, $suyas[0][8], 'los dias son un entero, no una formula');
        self::assertSame($suyas[0][6], $suyas[0][7], 'todo su saldo esta vencido');
    }

    public function testLaCobranzaPorDefectoSoloExportaAQuienTieneSaldoVencido(): void
    {
        $cliente = $this->clientePeligroso();
        // Una boleta que vence dentro de un anio: debe, pero todavia no esta vencida.
        (new BoletaRepository())->crear([
            'cliente_id' => $cliente['id'], 'concepto' => 'Por vencer', 'monto' => 500, 'moneda_codigo' => self::moneda(),
            'fecha_emision' => '2020-01-01', 'fecha_vencimiento' => (new DateTimeImmutable('+1 year'))->format('Y-m-d'),
        ]);
        $ids = static fn (array $filas): array => array_column(array_slice($filas, 1), 0);

        self::assertNotContains((string) $cliente['id'], $ids(self::leer($this->get('page=cobranza-csv')['cuerpo'])));
        self::assertContains((string) $cliente['id'], $ids(self::leer($this->get('page=cobranza-csv&alcance=todos')['cuerpo'])));
    }

    public function testLasPantallasEnlazanALaDescargaConSusFiltrosYSinLaPagina(): void
    {
        $cobros = $this->get('page=cobros&estado=pagada&cliente=ana&pagina=2')['cuerpo'];
        self::assertStringContainsString('href="?page=cobros-csv&amp;estado=pagada&amp;cliente=ana"', $cobros);
        self::assertStringNotContainsString('cobros-csv&amp;estado=pagada&amp;cliente=ana&amp;pagina', $cobros, 'la descarga no es de una pagina');

        $pagos = $this->get('page=pagos&cliente=ana&pagina=3')['cuerpo'];
        self::assertStringContainsString('href="?page=pagos-csv&amp;cliente=ana"', $pagos);

        $cobranza = $this->get('page=cobranza&alcance=todos&orden=saldo&pagina=2')['cuerpo'];
        self::assertStringContainsString('href="?page=cobranza-csv&amp;alcance=todos&amp;orden=saldo"', $cobranza);
        self::assertStringContainsString('Exportar CSV', $cobranza);
    }
}
