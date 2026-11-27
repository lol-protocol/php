<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\AnonimizacionRepository;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\IngresosYCobrosRepository;
use App\Repositories\PagoRepository;
use App\Repositories\SegmentacionRepository;
use PDOException;

/**
 * Migracion 022: anonimizar borra nombre, email y ciudad (del cliente y de su usuario del funnel), deja todo lo demas, se puede deshacer
 * 7 dias y despues es definitivo. Todo corre en la transaccion del test, que se deshace.
 */
final class AnonimizacionRepositoryTest extends IntegracionTestCase
{
    private AnonimizacionRepository $repo;
    private int $clienteId;
    private int $usuarioId;
    private int $boletaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AnonimizacionRepository();
        $db = Database::connection();

        $this->clienteId = (new ClienteRepository())->crear([
            'nombre' => 'Maria Perez', 'email' => 'maria.perez-' . uniqid() . '@example.com', 'segmento' => 'pro',
            'fecha_alta' => date('Y-m-d'), 'pais_codigo' => 'AR', 'ciudad' => 'Rosario', 'idioma' => 'Espanol',
            'genero' => 'Femenino', 'fecha_nacimiento' => date('Y-m-d', strtotime('-30 years')),
        ]);
        $db->prepare(
            "INSERT INTO auditoria (accion, entidad, entidad_id, detalle) VALUES ('crear', 'cliente', :id, :detalle)"
        )->execute([':id' => $this->clienteId, ':detalle' => "Cliente #{$this->clienteId}: Maria Perez (maria@example.com). Se confirmó."]);
        $db->prepare(
            "INSERT INTO usuarios_funnel (nombre, email, canal, pais_codigo, ciudad, idioma, genero, rango_de_edad, fecha_visita, cliente_id)
             VALUES ('Maria Perez', :email, 'ads', 'AR', 'Rosario', 'Espanol', 'Femenino', '25-34', CURRENT_DATE, :cliente)"
        )->execute([':email' => 'maria-funnel-' . uniqid() . '@example.com', ':cliente' => $this->clienteId]);
        $this->usuarioId = (int) $db->lastInsertId('usuarios_funnel_id_seq');

        $this->boletaId = (new BoletaRepository())->crear([
            'cliente_id' => $this->clienteId, 'concepto' => 'Boleta de Maria', 'monto' => 500, 'moneda_codigo' => 'ARS',
            'fecha_emision' => date('Y-m-d'), 'fecha_vencimiento' => date('Y-m-d', strtotime('+10 days')),
        ]);
        (new PagoRepository())->crear([
            'boleta_id' => $this->boletaId, 'cliente_id' => $this->clienteId, 'monto' => 200, 'moneda_codigo' => 'ARS',
            'fecha_pago' => date('Y-m-d'), 'metodo' => 'transferencia',
        ]);
    }

    /** @return array<string, mixed> */
    private function cliente(): array
    {
        $c = (new ClienteRepository())->porId($this->clienteId);
        self::assertNotNull($c);

        return $c;
    }

    /** @return array<string, mixed> */
    private function usuario(): array
    {
        $s = Database::connection()->prepare('SELECT * FROM usuarios_funnel WHERE id = :id');
        $s->execute([':id' => $this->usuarioId]);

        return $s->fetch() ?: [];
    }

    private function detalleDeLaAuditoria(): string
    {
        $s = Database::connection()->prepare("SELECT detalle FROM auditoria WHERE entidad = 'cliente' AND entidad_id = :id AND accion = 'crear'");
        $s->execute([':id' => $this->clienteId]);

        return (string) $s->fetchColumn();
    }

    public function testAnonimizarBorraNombreEmailYCiudadDelClienteYDelFunnel(): void
    {
        self::assertTrue($this->repo->anonimizar($this->clienteId));

        $c = $this->cliente();
        self::assertSame("Cliente anonimizado #{$this->clienteId}", $c['nombre']);
        self::assertSame("anonimizado-{$this->clienteId}@anonimizado.invalid", $c['email']);
        self::assertSame('(anonimizada)', $c['ciudad']);
        self::assertNotNull($c['anonimizado_en']);
        // Lo que queda.
        self::assertSame(['AR', 'Espanol', 'Femenino', '25-34', 'pro'], [$c['pais_codigo'], $c['idioma'], $c['genero'], $c['rango_de_edad'], $c['segmento']]);

        $u = $this->usuario();
        self::assertSame("Usuario anonimizado #{$this->usuarioId}", $u['nombre']);
        self::assertSame("anonimizado-funnel-{$this->usuarioId}@anonimizado.invalid", $u['email']);
        self::assertSame(['ads', 'AR'], [$u['canal'], $u['pais_codigo']], 'el canal y las etapas quedan');
    }

    public function testLaAuditoriaDelAltaQuedaSinDatosPersonales(): void
    {
        $this->repo->anonimizar($this->clienteId);

        $detalle = $this->detalleDeLaAuditoria();
        self::assertSame("Cliente #{$this->clienteId} (datos personales anonimizados)", $detalle);
        self::assertStringNotContainsString('Maria', $detalle);
        self::assertStringNotContainsString('@', $detalle);
    }

    public function testLasCifrasNoCambian(): void
    {
        $ingresos = new IngresosYCobrosRepository();
        $antes = [$ingresos->carteraPorAntiguedad(), (new SegmentacionRepository())->topPorDimensiones(50)['rango_edad']];
        $saldo = (new BoletaRepository())->porId($this->boletaId);

        $this->repo->anonimizar($this->clienteId);

        self::assertSame($antes[0], $ingresos->carteraPorAntiguedad(), 'la cartera es la misma');
        self::assertSame($antes[1], (new SegmentacionRepository())->topPorDimensiones(50)['rango_edad'], 'la segmentacion por edad es la misma');
        $despues = (new BoletaRepository())->porId($this->boletaId);
        self::assertNotNull($despues);
        self::assertNotNull($saldo);
        self::assertSame([$saldo['monto'], $saldo['pagado']], [$despues['monto'], $despues['pagado']], 'la boleta y su pago siguen');
    }

    public function testAnonimizarDosVecesNoPisaLosOriginalesGuardados(): void
    {
        $original = $this->cliente()['email'];
        self::assertTrue($this->repo->anonimizar($this->clienteId));
        self::assertFalse($this->repo->anonimizar($this->clienteId), 'la segunda no hace nada');
        self::assertFalse($this->repo->anonimizar(2147483000), 'un cliente que no existe');

        self::assertTrue($this->repo->deshacer($this->clienteId));
        self::assertSame($original, $this->cliente()['email'], 'volvio el email verdadero, no el anonimo');
    }

    public function testUnClienteAnonimizadoNoRecibeBoletasNiPagosNuevos(): void
    {
        $this->repo->anonimizar($this->clienteId);

        $boleta = fn () => Database::transaccion(fn (): int => (new BoletaRepository())->crear([
            'cliente_id' => $this->clienteId, 'concepto' => 'Nueva', 'monto' => 10, 'moneda_codigo' => 'ARS',
            'fecha_emision' => date('Y-m-d'), 'fecha_vencimiento' => date('Y-m-d'),
        ]));
        $pago = fn () => Database::transaccion(fn (): int => (new PagoRepository())->crear([
            'boleta_id' => null, 'cliente_id' => $this->clienteId, 'monto' => 10, 'moneda_codigo' => 'ARS',
            'fecha_pago' => date('Y-m-d'), 'metodo' => 'transferencia',
        ]));

        foreach (['boleta' => $boleta, 'pago' => $pago] as $que => $intento) {
            try {
                $intento();
                self::fail("la base tenia que rechazar una {$que} nueva");
            } catch (PDOException $e) {
                self::assertSame('23514', (string) $e->getCode(), $que);
                self::assertStringContainsString('anonimizado', $e->getMessage());
            }
        }
    }

    public function testLasBoletasYPagosQueYaTeniaSiguenAnulablesYAnulaNoInsertaPagos(): void
    {
        $this->repo->anonimizar($this->clienteId);

        self::assertTrue((new BoletaRepository())->anularSiEstabaActiva($this->boletaId), 'anular una boleta suya sigue andando');
    }

    public function testDeshacerDevuelveTodoYBorraLoGuardado(): void
    {
        $antes = $this->cliente();
        $usuarioAntes = $this->usuario();
        $auditoriaAntes = $this->detalleDeLaAuditoria();
        $this->repo->anonimizar($this->clienteId);

        self::assertNotNull($this->repo->deshacibleHasta($this->clienteId));
        self::assertTrue($this->repo->deshacer($this->clienteId));

        $c = $this->cliente();
        self::assertSame([$antes['nombre'], $antes['email'], $antes['ciudad']], [$c['nombre'], $c['email'], $c['ciudad']]);
        self::assertNull($c['anonimizado_en']);
        $u = $this->usuario();
        self::assertSame([$usuarioAntes['nombre'], $usuarioAntes['email']], [$u['nombre'], $u['email']]);
        self::assertNull($u['anonimizado_en']);
        self::assertSame($auditoriaAntes, $this->detalleDeLaAuditoria());
        self::assertNull($this->repo->deshacibleHasta($this->clienteId), 'ya no queda nada guardado');
        self::assertSame(
            0,
            (int) Database::connection()->query("SELECT COUNT(*) FROM datos_anonimizados_funnel WHERE cliente_id = {$this->clienteId}")->fetchColumn()
        );
        self::assertFalse($this->repo->deshacer($this->clienteId), 'deshacer dos veces no hace nada');
    }

    public function testElPlazoEsDeSieteDias(): void
    {
        $this->repo->anonimizar($this->clienteId);

        $dias = (float) Database::connection()->query(
            "SELECT extract(epoch FROM (vence_en - anonimizado_en)) / 86400 FROM datos_anonimizados WHERE cliente_id = {$this->clienteId}"
        )->fetchColumn();

        self::assertSame(7, AnonimizacionRepository::PLAZO_DIAS);
        self::assertEqualsWithDelta(7.0, $dias, 0.001);
    }

    public function testVencidoElPlazoNoSeDeshaceYLaPurgaBorraLosOriginales(): void
    {
        $db = Database::connection();
        $this->repo->anonimizar($this->clienteId);
        $db->exec("UPDATE datos_anonimizados SET vence_en = now() - interval '1 minute' WHERE cliente_id = {$this->clienteId}");

        self::assertNull($this->repo->deshacibleHasta($this->clienteId), 'vencido: ya no se ofrece deshacer');
        self::assertFalse($this->repo->deshacer($this->clienteId), 'y no se puede');
        self::assertSame("Cliente anonimizado #{$this->clienteId}", $this->cliente()['nombre']);
        self::assertGreaterThanOrEqual(1, $this->repo->pendientesDePurga());

        self::assertGreaterThanOrEqual(1, $this->repo->purgarVencidos());

        self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM datos_anonimizados WHERE cliente_id = {$this->clienteId}")->fetchColumn());
        self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM datos_anonimizados_funnel WHERE cliente_id = {$this->clienteId}")->fetchColumn());
        self::assertNotNull($this->cliente()['anonimizado_en'], 'sigue anonimizado: ahora para siempre');
    }

    public function testLaPurgaNoTocaLoQueTodaviaEstaEnPlazo(): void
    {
        $this->repo->anonimizar($this->clienteId);

        $this->repo->purgarVencidos();

        self::assertNotNull($this->repo->deshacibleHasta($this->clienteId));
    }

    public function testSiElEmailOriginalLoTomoOtroClienteNoSeDeshaceNada(): void
    {
        $emailOriginal = $this->cliente()['email'];
        $this->repo->anonimizar($this->clienteId);
        (new ClienteRepository())->crear([
            'nombre' => 'Otra persona', 'email' => $emailOriginal, 'segmento' => 'general', 'fecha_alta' => date('Y-m-d'), 'pais_codigo' => 'AR',
            'ciudad' => 'Salta', 'idioma' => 'Espanol', 'genero' => 'No especifica', 'fecha_nacimiento' => date('Y-m-d', strtotime('-40 years')),
        ]);

        try {
            Database::transaccion(fn (): bool => $this->repo->deshacer($this->clienteId));
            self::fail('el email ya lo usa otro cliente');
        } catch (PDOException $e) {
            self::assertSame('23505', (string) $e->getCode(), 'unique_violation');
        }

        self::assertSame("Cliente anonimizado #{$this->clienteId}", $this->cliente()['nombre'], 'queda anonimizado');
        self::assertNotNull($this->repo->deshacibleHasta($this->clienteId), 'y sigue en plazo');
    }

    public function testElSelectorDeClientesNoOfreceAUnAnonimizado(): void
    {
        $clientes = new ClienteRepository();
        self::assertContains($this->clienteId, array_map('intval', array_column($clientes->paraSelector(), 'id')));

        $this->repo->anonimizar($this->clienteId);

        self::assertNotContains($this->clienteId, array_map('intval', array_column($clientes->paraSelector(), 'id')));
    }

    public function testLaAuditoriaDeAnonimizarNoLlevaDatosPersonales(): void
    {
        Database::transaccion(function (): void {
            $this->repo->anonimizar($this->clienteId);
            AuditoriaRepository::auditar('anonimizar', 'cliente', $this->clienteId, "Cliente #{$this->clienteId} anonimizado.");
        });

        $detalle = (string) Database::connection()->query("SELECT string_agg(detalle, ' ') FROM auditoria WHERE entidad = 'cliente' AND entidad_id = {$this->clienteId}")->fetchColumn();
        self::assertStringNotContainsString('Maria', $detalle);
        self::assertStringNotContainsString('@example.com', $detalle);
    }
}
