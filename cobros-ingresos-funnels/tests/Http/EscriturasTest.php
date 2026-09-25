<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use App\EnvioUnico;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\PagoRepository;

/**
 * Los flujos que escriben, contra la app levantada. Todo lo que crean -en la
 * base de verdad, porque el servidor commitea- se borra al terminar.
 */
final class EscriturasTest extends HttpTestCase
{
    private const CLIENTE = 1;

    /** Una boleta de prueba del cliente 1, commiteada, que se borra (con todo lo que se le cuelgue) al final. */
    private function boletaDePrueba(): int
    {
        $cliente = (new ClienteRepository())->porId(self::CLIENTE);
        self::assertNotNull($cliente, 'estos tests asumen que el cliente #1 existe (lo trae el seed)');

        $id = (new BoletaRepository())->crear([
            'cliente_id' => self::CLIENTE,
            'concepto' => 'Boleta de prueba HTTP ' . uniqid(),
            'monto' => 1000,
            'moneda_codigo' => $cliente['moneda_codigo'],
            'fecha_emision' => '2020-01-01',
            'fecha_vencimiento' => '2020-02-01',
        ]);
        $this->alTerminar(static fn () => self::borrarBoletaConTodo($id));

        return $id;
    }

    private function pagoDePrueba(int $boletaId): int
    {
        $boleta = (new BoletaRepository())->porId($boletaId);
        self::assertNotNull($boleta);

        return (new PagoRepository())->crear([
            'boleta_id' => $boletaId,
            'cliente_id' => self::CLIENTE,
            'monto' => 100,
            'moneda_codigo' => $boleta['moneda_codigo'],
            'fecha_pago' => '2020-01-15',
            'metodo' => 'tarjeta',
        ]);
    }

    private static function borrarBoletaConTodo(int $id): void
    {
        $db = Database::connection();
        foreach ([
            "DELETE FROM auditoria WHERE entidad = 'pago' AND entidad_id IN (SELECT id FROM pagos WHERE boleta_id = :id)",
            "DELETE FROM auditoria WHERE entidad = 'nota_credito' AND entidad_id IN (SELECT id FROM notas_credito WHERE boleta_id = :id)",
            "DELETE FROM auditoria WHERE entidad = 'boleta' AND entidad_id = :id",
            'DELETE FROM notas_credito WHERE boleta_id = :id',
            'DELETE FROM pagos WHERE boleta_id = :id',
            'DELETE FROM boletas WHERE id = :id',
        ] as $sql) {
            $db->prepare($sql)->execute([':id' => $id]);
        }
    }

    private static function borrarEntidad(string $tabla, string $entidad, int $id): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM auditoria WHERE entidad = :entidad AND entidad_id = :id')->execute([':entidad' => $entidad, ':id' => $id]);
        $db->prepare("DELETE FROM {$tabla} WHERE id = :id")->execute([':id' => $id]);
    }

    private function olvidarToken(string $token): void
    {
        $this->alTerminar(static fn () => Database::connection()
            ->prepare('DELETE FROM envios_formulario WHERE token = :token')
            ->execute([':token' => $token]));
    }

    private static function contar(string $sql, array $parametros): int
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Reproduce el caso real: el mismo formulario de "Nuevo pago" enviado dos
     * veces (el doble clic llega antes de la redireccion) registraba dos
     * pagos iguales. Ahora el segundo envio recibe la misma redireccion.
     */
    public function testUnDobleEnvioDeNuevoPagoRegistraUnSoloPago(): void
    {
        $boletaId = $this->boletaDePrueba();
        $formulario = $this->get('page=pago-nuevo&cliente_id=' . self::CLIENTE)['cuerpo'];
        $datos = [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            EnvioUnico::CAMPO => self::campoOculto($formulario, EnvioUnico::CAMPO),
            'cliente_id' => self::CLIENTE,
            'boleta_id' => $boletaId,
            'monto' => '10.00',
            'fecha_pago' => '2020-01-20',
            'metodo' => 'tarjeta',
        ];
        $this->olvidarToken($datos[EnvioUnico::CAMPO]);

        $primero = $this->post('page=pago-nuevo&cliente_id=' . self::CLIENTE, $datos);
        $segundo = $this->post('page=pago-nuevo&cliente_id=' . self::CLIENTE, $datos);

        $this->assertStatus(302, $primero);
        $this->assertStatus(302, $segundo);
        self::assertSame($primero['location'], $segundo['location'], 'el reenvio va a donde fue el primero');
        self::assertSame(1, self::contar('SELECT COUNT(*) FROM pagos WHERE boleta_id = :id', [':id' => $boletaId]));
    }

    public function testUnDobleEnvioDeNuevaBoletaCreaUnaSola(): void
    {
        $formulario = $this->get('page=boleta-nueva')['cuerpo'];
        $concepto = 'Boleta doble envio ' . uniqid();
        $datos = [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            EnvioUnico::CAMPO => self::campoOculto($formulario, EnvioUnico::CAMPO),
            'cliente_id' => self::CLIENTE,
            'concepto' => $concepto,
            'monto' => '500.00',
            'fecha_emision' => '2020-03-01',
            'fecha_vencimiento' => '2020-04-01',
        ];
        $this->olvidarToken($datos[EnvioUnico::CAMPO]);

        $primero = $this->post('page=boleta-nueva', $datos);
        $this->assertStatus(302, $primero);
        $id = self::idDeLaRedireccion($primero, 'creada');
        $this->alTerminar(static fn () => self::borrarBoletaConTodo($id));

        $segundo = $this->post('page=boleta-nueva', $datos);

        $this->assertStatus(302, $segundo);
        self::assertSame($primero['location'], $segundo['location']);
        self::assertSame(1, self::contar('SELECT COUNT(*) FROM boletas WHERE concepto = :c', [':c' => $concepto]));
    }

    public function testUnAltaSinTokenDeEnvioNoCreaNadaYPideRecargar(): void
    {
        $boletaId = $this->boletaDePrueba();
        $formulario = $this->get('page=pago-nuevo&cliente_id=' . self::CLIENTE)['cuerpo'];

        $respuesta = $this->post('page=pago-nuevo&cliente_id=' . self::CLIENTE, [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            'cliente_id' => self::CLIENTE,
            'boleta_id' => $boletaId,
            'monto' => '10.00',
            'fecha_pago' => '2020-01-20',
            'metodo' => 'tarjeta',
        ]);

        $this->assertStatus(200, $respuesta);
        self::assertStringContainsString(htmlspecialchars(EnvioUnico::MENSAJE_SIN_TOKEN), $respuesta['cuerpo']);
        self::assertSame(0, self::contar('SELECT COUNT(*) FROM pagos WHERE boleta_id = :id', [':id' => $boletaId]));
    }

    /**
     * Regresion de la ronda anterior: con la boleta anulada (y su nota de
     * credito emitida), tocar el pago descontaba la plata dos veces.
     */
    public function testElPagoDeUnaBoletaAnuladaNoSePuedeAnularNiEditar(): void
    {
        $boletaId = $this->boletaDePrueba();
        $pagoId = $this->pagoDePrueba($boletaId);
        $csrf = self::campoOculto($this->get("page=boleta-anular&id={$boletaId}")['cuerpo'], 'csrf_token');

        $this->assertStatus(302, $this->post("page=boleta-anular&id={$boletaId}", ['csrf_token' => $csrf]));
        self::assertSame(1, self::contar('SELECT COUNT(*) FROM notas_credito WHERE boleta_id = :id', [':id' => $boletaId]));

        $this->assertStatus(409, $this->get("page=pago-anular&id={$pagoId}"));
        $this->assertStatus(409, $this->post("page=pago-anular&id={$pagoId}", ['csrf_token' => $csrf]));
        $this->assertStatus(409, $this->post("page=pago-editar&id={$pagoId}", [
            'csrf_token' => $csrf, 'monto' => '50.00', 'fecha_pago' => '2020-01-15', 'metodo' => 'tarjeta',
        ]));
        $pago = (new PagoRepository())->porId($pagoId);
        self::assertNotNull($pago);
        self::assertFalse($pago['anulada'], 'el pago quedo como estaba');
    }

    /**
     * Cada flujo que escribe tiene que auditar dentro de una transaccion:
     * AuditoriaRepository lo exige y, si no, el flujo daria un 500. Esto
     * recorre los que no cubren los tests de arriba y verifica que cada uno
     * deje exactamente una entrada de auditoria.
     */
    public function testCadaFlujoDeEscrituraAuditaUnaVez(): void
    {
        $boletaId = $this->boletaDePrueba();
        $pagoId = $this->pagoDePrueba($boletaId);
        $csrf = self::campoOculto($this->get('page=cliente-nuevo')['cuerpo'], 'csrf_token');
        $auditorias = static fn (string $entidad, int $id): int => self::contar(
            'SELECT COUNT(*) FROM auditoria WHERE entidad = :entidad AND entidad_id = :id',
            [':entidad' => $entidad, ':id' => $id]
        );

        $this->assertStatus(302, $this->post("page=boleta-editar&id={$boletaId}", [
            'csrf_token' => $csrf, 'concepto' => 'Boleta editada por HTTP', 'monto' => '900.00',
            'fecha_emision' => '2020-01-01', 'fecha_vencimiento' => '2020-02-15',
        ]), 'editar boleta');
        self::assertSame(1, $auditorias('boleta', $boletaId));

        $this->assertStatus(302, $this->post("page=pago-editar&id={$pagoId}", [
            'csrf_token' => $csrf, 'monto' => '150.00', 'fecha_pago' => '2020-01-16', 'metodo' => 'efectivo',
        ]), 'editar pago');
        self::assertSame(1, $auditorias('pago', $pagoId));

        $cliente = $this->post('page=cliente-nuevo', [
            'csrf_token' => $csrf, 'nombre' => 'Cliente HTTP', 'email' => 'cliente-http-' . uniqid() . '@example.com',
            'pais_codigo' => 'AR', 'ciudad' => 'Rosario', 'idioma' => 'Espanol', 'genero' => 'No especifica',
            'fecha_nacimiento' => '1990-05-05', 'segmento' => 'general',
        ]);
        $this->assertStatus(302, $cliente, 'alta de cliente');
        $clienteId = self::idDeLaRedireccion($cliente, 'id');
        $this->alTerminar(static fn () => self::borrarEntidad('clientes', 'cliente', $clienteId));
        self::assertSame(1, $auditorias('cliente', $clienteId));
    }
}
