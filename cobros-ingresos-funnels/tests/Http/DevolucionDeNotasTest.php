<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\NotaCreditoRepository;
use App\Repositories\PagoRepository;

/**
 * La pantalla de Notas de credito y el registro de cuando se devolvio la plata,
 * contra la app levantada. Todo lo que crean (el servidor commitea) se borra al
 * terminar.
 */
final class DevolucionDeNotasTest extends HttpTestCase
{
    private const CLIENTE = 1;
    private const FECHA_DE_LA_NOTA = '2020-03-01';

    /**
     * Una nota de credito de prueba (con su boleta) del cliente 1, emitida el
     * 2020-03-01, que se borra con todo lo que se le cuelgue al final.
     *
     * @return array{id: int, boletaId: int, motivo: string}
     */
    private function notaDePrueba(): array
    {
        $boletaId = $this->boletaDePrueba();
        $boleta = (new BoletaRepository())->porId($boletaId);
        self::assertNotNull($boleta);
        $motivo = 'Devolucion HTTP ' . uniqid();

        $id = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boletaId,
            'cliente_id' => self::CLIENTE,
            'monto' => 100,
            'moneda_codigo' => $boleta['moneda_codigo'],
            'fecha' => self::FECHA_DE_LA_NOTA,
            'motivo' => $motivo,
        ]);

        return ['id' => $id, 'boletaId' => $boletaId, 'motivo' => $motivo];
    }

    private function boletaDePrueba(): int
    {
        $cliente = (new ClienteRepository())->porId(self::CLIENTE);
        self::assertNotNull($cliente, 'estos tests asumen que el cliente #1 existe (lo trae el seed)');

        $id = (new BoletaRepository())->crear([
            'cliente_id' => self::CLIENTE,
            'concepto' => 'Boleta de prueba de notas ' . uniqid(),
            'monto' => 1000,
            'moneda_codigo' => $cliente['moneda_codigo'],
            'fecha_emision' => '2020-01-01',
            'fecha_vencimiento' => '2020-02-01',
        ]);
        $this->alTerminar(static fn () => self::borrarBoletaConTodo($id));

        return $id;
    }

    private static function borrarBoletaConTodo(int $id): void
    {
        $db = Database::connection();
        foreach ([
            "DELETE FROM auditoria WHERE entidad = 'nota_credito' AND entidad_id IN (SELECT id FROM notas_credito WHERE boleta_id = :id)",
            "DELETE FROM auditoria WHERE entidad = 'pago' AND entidad_id IN (SELECT id FROM pagos WHERE boleta_id = :id)",
            "DELETE FROM auditoria WHERE entidad = 'boleta' AND entidad_id = :id",
            'DELETE FROM notas_credito WHERE boleta_id = :id',
            'DELETE FROM pagos WHERE boleta_id = :id',
            'DELETE FROM boletas WHERE id = :id',
        ] as $sql) {
            $db->prepare($sql)->execute([':id' => $id]);
        }
    }

    private static function fechaDeDevolucion(int $notaId): ?string
    {
        $nota = (new NotaCreditoRepository())->porId($notaId);
        self::assertNotNull($nota);

        return $nota['fecha_devolucion'];
    }

    private static function auditoriasDeDevolucion(int $notaId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM auditoria WHERE entidad = 'nota_credito' AND entidad_id = :id AND accion = 'devolver'"
        );
        $stmt->execute([':id' => $notaId]);

        return (int) $stmt->fetchColumn();
    }

    /** El token CSRF de la sesion, sacado del formulario de devolucion de esa nota. */
    private function csrfDe(int $notaId): string
    {
        return self::campoOculto($this->get("page=nota-devolver&id={$notaId}")['cuerpo'], 'csrf_token');
    }

    public function testLaNotaQueSeEmiteAlAnularUnaBoletaAparecePendienteEnLaPantalla(): void
    {
        $boletaId = $this->boletaDePrueba();
        $boleta = (new BoletaRepository())->porId($boletaId);
        self::assertNotNull($boleta);
        (new PagoRepository())->crear([
            'boleta_id' => $boletaId, 'cliente_id' => self::CLIENTE, 'monto' => 100,
            'moneda_codigo' => $boleta['moneda_codigo'], 'fecha_pago' => '2020-01-15', 'metodo' => 'tarjeta',
        ]);
        $csrf = self::campoOculto($this->get("page=boleta-anular&id={$boletaId}")['cuerpo'], 'csrf_token');

        $this->assertStatus(302, $this->post("page=boleta-anular&id={$boletaId}", ['csrf_token' => $csrf]));

        $pantalla = $this->get('page=notas');
        $this->assertStatus(200, $pantalla);
        self::assertStringContainsString("Anulación de la boleta #{$boletaId}", $pantalla['cuerpo'], 'la nota emitida figura entre las pendientes');
        self::assertStringContainsString('Registrar devolución', $pantalla['cuerpo']);
    }

    public function testLaPantallaMuestraLasPendientesPorDefectoYFiltraPorEstado(): void
    {
        $nota = $this->notaDePrueba();

        self::assertStringContainsString($nota['motivo'], $this->get('page=notas')['cuerpo'], 'sin filtro: las pendientes');
        self::assertStringContainsString($nota['motivo'], $this->get('page=notas&estado=pendientes')['cuerpo']);
        self::assertStringNotContainsString($nota['motivo'], $this->get('page=notas&estado=devueltas')['cuerpo']);
        self::assertStringContainsString($nota['motivo'], $this->get('page=notas&estado=todas')['cuerpo']);
    }

    public function testUnEstadoInventadoAvisaYMuestraLasPendientes(): void
    {
        $nota = $this->notaDePrueba();

        $pantalla = $this->get('page=notas&estado=inventado');

        $this->assertStatus(200, $pantalla);
        self::assertStringContainsString('El estado pedido no existe; se muestran las notas pendientes de devolver.', $pantalla['cuerpo']);
        self::assertStringContainsString($nota['motivo'], $pantalla['cuerpo']);
    }

    public function testRegistrarLaDevolucionLaMarcaLaAuditaYLaConfirma(): void
    {
        $nota = $this->notaDePrueba();
        $formulario = $this->get("page=nota-devolver&id={$nota['id']}");
        $this->assertStatus(200, $formulario);
        self::assertStringContainsString('name="fecha_devolucion"', $formulario['cuerpo']);
        self::assertStringContainsString('min="' . self::FECHA_DE_LA_NOTA . '"', $formulario['cuerpo'], 'el formulario no deja elegir antes de la nota');
        self::assertStringContainsString('max="' . date('Y-m-d') . '"', $formulario['cuerpo'], 'ni despues de hoy');

        $respuesta = $this->post("page=nota-devolver&id={$nota['id']}", [
            'csrf_token' => self::campoOculto($formulario['cuerpo'], 'csrf_token'),
            'fecha_devolucion' => '2020-03-05',
        ]);

        $this->assertStatus(302, $respuesta);
        self::assertSame("?page=notas&devuelta={$nota['id']}", $respuesta['location']);
        self::assertSame('2020-03-05', self::fechaDeDevolucion($nota['id']));
        self::assertSame(1, self::auditoriasDeDevolucion($nota['id']), 'deja exactamente una entrada de auditoria');

        $pendientes = $this->get((string) $respuesta['location']);
        self::assertStringContainsString("Devolución de la nota de crédito #{$nota['id']} registrada.", $pendientes['cuerpo']);
        self::assertStringNotContainsString($nota['motivo'], $pendientes['cuerpo'], 'ya no figura entre las pendientes');
        $devueltas = $this->get('page=notas&estado=devueltas')['cuerpo'];
        self::assertStringContainsString($nota['motivo'], $devueltas);
        self::assertStringContainsString('Devuelta el 2020-03-05', $devueltas);
        self::assertStringContainsString('Devolvió', $this->get('page=auditoria')['cuerpo'], 'la auditoria traduce la accion');
    }

    public function testUnDobleEnvioRegistraYAuditaUnaSolaVez(): void
    {
        $nota = $this->notaDePrueba();
        $csrf = $this->csrfDe($nota['id']);
        $datos = ['csrf_token' => $csrf, 'fecha_devolucion' => '2020-03-05'];

        $primero = $this->post("page=nota-devolver&id={$nota['id']}", $datos);
        $segundo = $this->post("page=nota-devolver&id={$nota['id']}", ['fecha_devolucion' => '2020-03-09'] + $datos);

        $this->assertStatus(302, $primero);
        $this->assertStatus(302, $segundo);
        self::assertSame($primero['location'], $segundo['location'], 'el segundo va a donde fue el primero');
        self::assertSame('2020-03-05', self::fechaDeDevolucion($nota['id']), 'queda la primera fecha');
        self::assertSame(1, self::auditoriasDeDevolucion($nota['id']));
    }

    public function testUnaNotaYaDevueltaMuestraSuFechaYNoPideOtra(): void
    {
        $nota = $this->notaDePrueba();
        (new NotaCreditoRepository())->registrarDevolucionSiEstabaPendiente($nota['id'], '2020-03-05');

        $formulario = $this->get("page=nota-devolver&id={$nota['id']}");

        $this->assertStatus(200, $formulario);
        self::assertStringContainsString('Esta devolución ya se registró', $formulario['cuerpo']);
        self::assertStringContainsString('2020-03-05', $formulario['cuerpo']);
        self::assertStringNotContainsString('name="fecha_devolucion"', $formulario['cuerpo'], 'no hay formulario para registrarla otra vez');
    }

    public function testLaFechaMalaSeRechazaConSuMensajeYNoSeRegistraNada(): void
    {
        $nota = $this->notaDePrueba();
        $csrf = $this->csrfDe($nota['id']);
        $casos = [
            'vacía' => ['', 'La fecha de devolución no es válida.'],
            'que no existe' => ['2020-02-30', 'La fecha de devolución no es válida.'],
            'anterior a la nota' => ['2020-02-29', 'La fecha de devolución no puede ser anterior a la nota de crédito (' . self::FECHA_DE_LA_NOTA . ').'],
            'futura' => [date('Y-m-d', strtotime('+1 day')), 'La fecha de devolución no puede ser futura.'],
        ];

        foreach ($casos as $nombre => [$fecha, $mensaje]) {
            $respuesta = $this->post("page=nota-devolver&id={$nota['id']}", ['csrf_token' => $csrf, 'fecha_devolucion' => $fecha]);

            $this->assertStatus(200, $respuesta, "fecha {$nombre}");
            self::assertStringContainsString($mensaje, $respuesta['cuerpo'], "fecha {$nombre}");
            self::assertNull(self::fechaDeDevolucion($nota['id']), "fecha {$nombre}: no se registro");
            self::assertSame(0, self::auditoriasDeDevolucion($nota['id']), "fecha {$nombre}: no se audito");
        }
    }

    public function testUnaNotaQueNoExisteDa404(): void
    {
        $nota = $this->notaDePrueba();
        $csrf = $this->csrfDe($nota['id']);

        $this->assertStatus(404, $this->get('page=nota-devolver&id=2000000000'));
        $this->assertStatus(404, $this->post('page=nota-devolver&id=2000000000', ['csrf_token' => $csrf, 'fecha_devolucion' => '2020-03-05']));
        $this->assertStatus(404, $this->get('page=nota-devolver'), 'sin id');
    }

    public function testSinElTokenDeSeguridadNoSeRegistraNada(): void
    {
        $nota = $this->notaDePrueba();

        $this->assertStatus(403, $this->post("page=nota-devolver&id={$nota['id']}", ['fecha_devolucion' => '2020-03-05']));

        self::assertNull(self::fechaDeDevolucion($nota['id']));
    }

    public function testElDashboardLaFichaYElMenuMuestranLaDevolucion(): void
    {
        $nota = $this->notaDePrueba();

        $dashboard = $this->get('page=dashboard')['cuerpo'];
        self::assertStringContainsString('Devoluciones pendientes', $dashboard);
        self::assertStringContainsString('<a href="?page=notas">', $dashboard, 'el KPI lleva a la pantalla de notas');
        self::assertStringContainsString('sin devolver', $dashboard);
        self::assertStringContainsString('href="?page=notas"', $dashboard, 'el menu tiene la pantalla');

        $ficha = $this->get('page=cliente&id=' . self::CLIENTE)['cuerpo'];
        self::assertStringContainsString("page=nota-devolver&amp;id={$nota['id']}", $ficha, 'la ficha ofrece registrarla');

        (new NotaCreditoRepository())->registrarDevolucionSiEstabaPendiente($nota['id'], '2020-03-05');
        $ficha = $this->get('page=cliente&id=' . self::CLIENTE)['cuerpo'];
        self::assertStringContainsString('Devuelta el 2020-03-05', $ficha);
        self::assertStringNotContainsString("page=nota-devolver&amp;id={$nota['id']}", $ficha, 'ya no la ofrece');
    }
}
