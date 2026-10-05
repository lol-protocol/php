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

    /**
     * Regresion: cliente-nuevo era el unico alta sin EnvioUnico. El doble
     * envio no duplicaba el cliente (el email es UNIQUE) pero el reenvio
     * chocaba contra esa constraint y mostraba "ya existe" en vez de
     * redirigir como boleta-nueva y pago-nuevo.
     */
    public function testUnDobleEnvioDeNuevoClienteCreaUnoSolo(): void
    {
        $formulario = $this->get('page=cliente-nuevo')['cuerpo'];
        $email = 'cliente-doble-' . uniqid() . '@example.com';
        $datos = [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            EnvioUnico::CAMPO => self::campoOculto($formulario, EnvioUnico::CAMPO),
            'nombre' => 'Cliente doble envio',
            'email' => $email,
            'pais_codigo' => 'AR',
            'ciudad' => 'Rosario',
            'idioma' => 'Espanol',
            'genero' => 'No especifica',
            'fecha_nacimiento' => '1990-05-05',
            'segmento' => 'general',
        ];
        $this->olvidarToken($datos[EnvioUnico::CAMPO]);

        $primero = $this->post('page=cliente-nuevo', $datos);
        $this->assertStatus(302, $primero);
        $id = self::idDeLaRedireccion($primero, 'id');
        $this->alTerminar(static fn () => self::borrarEntidad('clientes', 'cliente', $id));

        $segundo = $this->post('page=cliente-nuevo', $datos);

        $this->assertStatus(302, $segundo);
        self::assertSame($primero['location'], $segundo['location'], 'el reenvio va a donde fue el primero, no a un error de email duplicado');
        self::assertSame(1, self::contar('SELECT COUNT(*) FROM clientes WHERE email = :e', [':e' => $email]));
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
     * Regresion: tras un error de validacion, "Nuevo pago" devolvia el formulario
     * vacio y la boleta elegida volvia a "Anticipo": quien corregia solo el
     * monto y reenviaba registraba el pago sin boleta, sin ningun aviso.
     */
    public function testUnErrorEnNuevoPagoConservaLoQueElUsuarioElegio(): void
    {
        $boletaId = $this->boletaDePrueba();
        $formulario = $this->get('page=pago-nuevo&cliente_id=' . self::CLIENTE)['cuerpo'];

        $respuesta = $this->post('page=pago-nuevo&cliente_id=' . self::CLIENTE, [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            EnvioUnico::CAMPO => self::campoOculto($formulario, EnvioUnico::CAMPO),
            'cliente_id' => self::CLIENTE,
            'boleta_id' => $boletaId,
            'monto' => '999999.00',
            'fecha_pago' => '2020-03-05',
            'metodo' => 'tarjeta',
        ]);

        $this->assertStatus(200, $respuesta);
        $cuerpo = $respuesta['cuerpo'];
        self::assertStringContainsString('El monto supera el saldo pendiente de la boleta.', $cuerpo);
        self::assertMatchesRegularExpression('/<option value="' . $boletaId . '"\s+selected/', $cuerpo, 'la boleta elegida sigue elegida');
        self::assertMatchesRegularExpression('/name="monto"[^>]*value="999999.00"/', $cuerpo);
        self::assertMatchesRegularExpression('/name="fecha_pago"[^>]*value="2020-03-05"/', $cuerpo);
        self::assertMatchesRegularExpression('/<option value="tarjeta"\s+selected/', $cuerpo);
        self::assertSame(0, self::contar('SELECT COUNT(*) FROM pagos WHERE boleta_id = :id', [':id' => $boletaId]));
    }

    /**
     * Regresion: "Nuevo cliente" repoblaba los campos de texto y el pais, pero
     * genero y segmento volvian a la primera opcion: corregir solo la ciudad y
     * reenviar guardaba al cliente con un genero y un segmento que no eligio.
     */
    public function testUnErrorEnNuevoClienteConservaGeneroYSegmento(): void
    {
        $formulario = $this->get('page=cliente-nuevo')['cuerpo'];

        $respuesta = $this->post('page=cliente-nuevo', [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            EnvioUnico::CAMPO => self::campoOculto($formulario, EnvioUnico::CAMPO),
            'nombre' => 'Cliente con error',
            'email' => 'cliente-error-' . uniqid() . '@example.com',
            'pais_codigo' => 'AR',
            'ciudad' => '',
            'idioma' => 'Espanol',
            'genero' => 'Masculino',
            'fecha_nacimiento' => '1990-05-05',
            'segmento' => 'enterprise',
        ]);

        $this->assertStatus(200, $respuesta);
        self::assertStringContainsString('Completá todos los campos obligatorios.', $respuesta['cuerpo']);
        self::assertMatchesRegularExpression('/<option value="Masculino"\s+selected/', $respuesta['cuerpo']);
        self::assertMatchesRegularExpression('/<option value="enterprise"\s+selected/', $respuesta['cuerpo']);
    }

    /**
     * Regresion: el metodo de pago solo lo restringia el <select> del navegador.
     * Un POST con "bitcoin" se guardaba y aparecia como una barra mas en Pagos.
     */
    public function testNuevoPagoRechazaUnMetodoFueraDeLaLista(): void
    {
        $formulario = $this->get('page=pago-nuevo&cliente_id=' . self::CLIENTE)['cuerpo'];
        $token = self::campoOculto($formulario, EnvioUnico::CAMPO);
        $this->olvidarToken($token);
        // Si la validacion faltara, el pago queda commiteado: se borra igual.
        $this->alTerminar(static function (): void {
            $db = Database::connection();
            $db->exec("DELETE FROM auditoria WHERE entidad = 'pago' AND entidad_id IN (SELECT id FROM pagos WHERE metodo = 'bitcoin')");
            $db->exec("DELETE FROM pagos WHERE metodo = 'bitcoin'");
        });

        $respuesta = $this->post('page=pago-nuevo&cliente_id=' . self::CLIENTE, [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            EnvioUnico::CAMPO => $token,
            'cliente_id' => self::CLIENTE,
            'boleta_id' => '',
            'monto' => '12.34',
            'fecha_pago' => '2020-04-01',
            'metodo' => 'bitcoin',
        ]);

        $this->assertStatus(200, $respuesta);
        self::assertStringContainsString('Elegí un método de pago válido.', $respuesta['cuerpo']);
        self::assertSame(0, self::contar("SELECT COUNT(*) FROM pagos WHERE metodo = 'bitcoin'", []));
    }

    public function testEditarPagoRechazaUnMetodoFueraDeLaLista(): void
    {
        $pagoId = $this->pagoDePrueba($this->boletaDePrueba());
        $formulario = $this->get("page=pago-editar&id={$pagoId}")['cuerpo'];

        $respuesta = $this->post("page=pago-editar&id={$pagoId}", [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            'monto' => '100.00',
            'fecha_pago' => '2020-01-15',
            'metodo' => 'bitcoin',
        ]);

        $this->assertStatus(200, $respuesta);
        self::assertStringContainsString('Elegí un método de pago válido.', $respuesta['cuerpo']);
        $pago = (new PagoRepository())->porId($pagoId);
        self::assertNotNull($pago);
        self::assertSame('tarjeta', $pago['metodo'], 'el pago quedo como estaba');
    }

    /** Regresion: genero y segmento solo los restringia el <select>; un POST con "Alienigena" o "vip" se guardaba. */
    public function testNuevoClienteRechazaGeneroYSegmentoFueraDeLaLista(): void
    {
        $formulario = $this->get('page=cliente-nuevo')['cuerpo'];
        $email = 'cliente-lista-' . uniqid() . '@example.com';
        $this->alTerminar(static function () use ($email): void {
            $db = Database::connection();
            $db->prepare("DELETE FROM auditoria WHERE entidad = 'cliente' AND entidad_id IN (SELECT id FROM clientes WHERE email = :e)")->execute([':e' => $email]);
            $db->prepare('DELETE FROM clientes WHERE email = :e')->execute([':e' => $email]);
        });
        $datos = [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            EnvioUnico::CAMPO => self::campoOculto($formulario, EnvioUnico::CAMPO),
            'nombre' => 'Cliente fuera de lista',
            'email' => $email,
            'pais_codigo' => 'AR',
            'ciudad' => 'Rosario',
            'idioma' => 'Espanol',
            'genero' => 'Alienigena',
            'fecha_nacimiento' => '1990-05-05',
            'segmento' => 'general',
        ];
        $this->olvidarToken($datos[EnvioUnico::CAMPO]);

        $generoMalo = $this->post('page=cliente-nuevo', $datos);
        $this->assertStatus(200, $generoMalo);
        self::assertStringContainsString('Elegí un género válido.', $generoMalo['cuerpo']);

        // "0" es falso en PHP: tampoco puede colarse como "no eligio" y quedar con el valor por defecto.
        $generoCero = $this->post('page=cliente-nuevo', ['genero' => '0'] + $datos);
        $this->assertStatus(200, $generoCero);
        self::assertStringContainsString('Elegí un género válido.', $generoCero['cuerpo']);

        $segmentoMalo = $this->post('page=cliente-nuevo', ['genero' => 'Masculino', 'segmento' => 'vip'] + $datos);
        $this->assertStatus(200, $segmentoMalo);
        self::assertStringContainsString('Elegí un segmento válido.', $segmentoMalo['cuerpo']);

        self::assertSame(0, self::contar('SELECT COUNT(*) FROM clientes WHERE email = :e', [':e' => $email]));
    }

    /**
     * Regresion: el alta aceptaba cualquier fecha de nacimiento real, futura
     * incluida, y la segmentacion por edad la contaba como "18-24". Un menor
     * de edad, en cambio, es un cliente posible: se acepta y sale en su tramo.
     * Futuro es "+2 dias" y no "manana" para no depender de la zona horaria
     * de quien corre el test frente a la de la app.
     */
    public function testNuevoClienteRechazaUnNacimientoFuturoPeroAceptaAUnMenor(): void
    {
        $formulario = $this->get('page=cliente-nuevo')['cuerpo'];
        $email = 'cliente-nacimiento-' . uniqid() . '@example.com';
        $this->alTerminar(static function () use ($email): void {
            $db = Database::connection();
            $db->prepare("DELETE FROM auditoria WHERE entidad = 'cliente' AND entidad_id IN (SELECT id FROM clientes WHERE email = :e)")->execute([':e' => $email]);
            $db->prepare('DELETE FROM clientes WHERE email = :e')->execute([':e' => $email]);
        });
        $datos = [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            EnvioUnico::CAMPO => self::campoOculto($formulario, EnvioUnico::CAMPO),
            'nombre' => 'Cliente con nacimiento raro',
            'email' => $email,
            'pais_codigo' => 'AR',
            'ciudad' => 'Rosario',
            'idioma' => 'Espanol',
            'genero' => 'No especifica',
            'fecha_nacimiento' => date('Y-m-d', strtotime('+2 days')),
            'segmento' => 'general',
        ];
        $this->olvidarToken($datos[EnvioUnico::CAMPO]);

        $futuro = $this->post('page=cliente-nuevo', $datos);
        $this->assertStatus(200, $futuro);
        self::assertStringContainsString('La fecha de nacimiento no puede ser posterior a hoy.', $futuro['cuerpo']);
        self::assertSame(0, self::contar('SELECT COUNT(*) FROM clientes WHERE email = :e', [':e' => $email]));

        $menor = $this->post('page=cliente-nuevo', ['fecha_nacimiento' => date('Y-m-d', strtotime('-16 years'))] + $datos);
        $this->assertStatus(302, $menor);
        self::assertSame(1, self::contar('SELECT COUNT(*) FROM clientes WHERE email = :e', [':e' => $email]));
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
     * Regresion: boleta/pago/cliente tenian traduccion en la columna Acción
     * de Auditoria, pero nota_credito (agregada en una ronda posterior) se
     * quedo afuera del mapa y aparecia como el nombre crudo de la columna.
     */
    public function testLaAuditoriaTraduceNotaDeCredito(): void
    {
        $boletaId = $this->boletaDePrueba();
        $this->pagoDePrueba($boletaId);
        $csrf = self::campoOculto($this->get("page=boleta-anular&id={$boletaId}")['cuerpo'], 'csrf_token');

        $this->assertStatus(302, $this->post("page=boleta-anular&id={$boletaId}", ['csrf_token' => $csrf]));

        self::assertStringContainsString('Nota de crédito', $this->get('page=auditoria')['cuerpo']);
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
        $formularioCliente = $this->get('page=cliente-nuevo')['cuerpo'];
        $csrf = self::campoOculto($formularioCliente, 'csrf_token');
        $envioCliente = self::campoOculto($formularioCliente, EnvioUnico::CAMPO);
        $this->olvidarToken($envioCliente);
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
            'csrf_token' => $csrf, EnvioUnico::CAMPO => $envioCliente,
            'nombre' => 'Cliente HTTP', 'email' => 'cliente-http-' . uniqid() . '@example.com',
            'pais_codigo' => 'AR', 'ciudad' => 'Rosario', 'idioma' => 'Espanol', 'genero' => 'No especifica',
            'fecha_nacimiento' => '1990-05-05', 'segmento' => 'general',
        ]);
        $this->assertStatus(302, $cliente, 'alta de cliente');
        $clienteId = self::idDeLaRedireccion($cliente, 'id');
        $this->alTerminar(static fn () => self::borrarEntidad('clientes', 'cliente', $clienteId));
        self::assertSame(1, $auditorias('cliente', $clienteId));
    }

    /**
     * Regresion: las redirecciones de las altas, ediciones y anulaciones
     * llevaban el id (?creada=ID, ?creado=ID...) pero ninguna pantalla lo
     * leia, asi que nada confirmaba que se habia hecho. Recorre cada flujo y
     * sigue la redireccion como lo haria el navegador.
     */
    public function testCadaAltaEdicionYAnulacionSeConfirmaEnLaPantallaDeDestino(): void
    {
        $seguir = fn (array $respuesta): string => $this->get(substr((string) $respuesta['location'], 1))['cuerpo'];
        $formulario = $this->get('page=boleta-nueva')['cuerpo'];
        $csrf = self::campoOculto($formulario, 'csrf_token');
        $envioBoleta = self::campoOculto($formulario, EnvioUnico::CAMPO);
        $this->olvidarToken($envioBoleta);

        // Boleta: alta, edicion y anulacion.
        $alta = $this->post('page=boleta-nueva', [
            'csrf_token' => $csrf, EnvioUnico::CAMPO => $envioBoleta, 'cliente_id' => self::CLIENTE,
            'concepto' => 'Boleta de confirmaciones ' . uniqid(), 'monto' => '500.00',
            'fecha_emision' => '2020-03-01', 'fecha_vencimiento' => '2020-04-01',
        ]);
        $this->assertStatus(302, $alta);
        $boletaId = self::idDeLaRedireccion($alta, 'creada');
        $this->alTerminar(static fn () => self::borrarBoletaConTodo($boletaId));
        self::assertStringContainsString("Boleta #{$boletaId} creada.", $seguir($alta));

        $edicion = $this->post("page=boleta-editar&id={$boletaId}", [
            'csrf_token' => $csrf, 'concepto' => 'Boleta editada', 'monto' => '450.00',
            'fecha_emision' => '2020-03-01', 'fecha_vencimiento' => '2020-04-01',
        ]);
        $this->assertStatus(302, $edicion);
        self::assertStringContainsString("Boleta #{$boletaId} actualizada.", $seguir($edicion));

        // Pago: edicion y anulacion (el alta pasa por el formulario con su token de envio, mas abajo).
        $pagoId = $this->pagoDePrueba($boletaId);
        $edicionPago = $this->post("page=pago-editar&id={$pagoId}", [
            'csrf_token' => $csrf, 'monto' => '120.00', 'fecha_pago' => '2020-03-05', 'metodo' => 'efectivo',
        ]);
        $this->assertStatus(302, $edicionPago);
        self::assertStringContainsString("Pago #{$pagoId} actualizado.", $seguir($edicionPago));

        $anulacionPago = $this->post("page=pago-anular&id={$pagoId}", ['csrf_token' => $csrf]);
        $this->assertStatus(302, $anulacionPago);
        self::assertStringContainsString("Pago #{$pagoId} anulado.", $seguir($anulacionPago));

        $anulacion = $this->post("page=boleta-anular&id={$boletaId}", ['csrf_token' => $csrf]);
        $this->assertStatus(302, $anulacion);
        self::assertStringContainsString("Boleta #{$boletaId} anulada.", $seguir($anulacion));
    }

    public function testElAltaDeUnPagoYDeUnClienteSeConfirmanEnSuPantalla(): void
    {
        $seguir = fn (array $respuesta): string => $this->get(substr((string) $respuesta['location'], 1))['cuerpo'];
        $boletaId = $this->boletaDePrueba();

        $formularioPago = $this->get('page=pago-nuevo&cliente_id=' . self::CLIENTE)['cuerpo'];
        $envioPago = self::campoOculto($formularioPago, EnvioUnico::CAMPO);
        $this->olvidarToken($envioPago);
        $pago = $this->post('page=pago-nuevo&cliente_id=' . self::CLIENTE, [
            'csrf_token' => self::campoOculto($formularioPago, 'csrf_token'), EnvioUnico::CAMPO => $envioPago,
            'cliente_id' => self::CLIENTE, 'boleta_id' => $boletaId, 'monto' => '100.00',
            'fecha_pago' => '2020-01-20', 'metodo' => 'tarjeta',
        ]);
        $this->assertStatus(302, $pago);
        $pagoId = self::idDeLaRedireccion($pago, 'creado');
        self::assertStringContainsString("Pago #{$pagoId} registrado.", $seguir($pago));

        $formularioCliente = $this->get('page=cliente-nuevo')['cuerpo'];
        $envioCliente = self::campoOculto($formularioCliente, EnvioUnico::CAMPO);
        $this->olvidarToken($envioCliente);
        $cliente = $this->post('page=cliente-nuevo', [
            'csrf_token' => self::campoOculto($formularioCliente, 'csrf_token'), EnvioUnico::CAMPO => $envioCliente,
            'nombre' => 'Cliente confirmado', 'email' => 'cliente-confirmado-' . uniqid() . '@example.com',
            'pais_codigo' => 'AR', 'ciudad' => 'Rosario', 'idioma' => 'Espanol', 'genero' => 'No especifica',
            'fecha_nacimiento' => '1990-05-05', 'segmento' => 'general',
        ]);
        $this->assertStatus(302, $cliente);
        $clienteId = self::idDeLaRedireccion($cliente, 'id');
        $this->alTerminar(static fn () => self::borrarEntidad('clientes', 'cliente', $clienteId));
        self::assertStringContainsString('Cliente creado.', $seguir($cliente));
    }

    public function testUnaPantallaSinRedireccionNoMuestraConfirmaciones(): void
    {
        foreach (['page=cobros', 'page=pagos', 'page=cliente&id=' . self::CLIENTE] as $query) {
            self::assertStringNotContainsString('class="aviso', $this->get($query)['cuerpo'], $query);
        }
    }

    /**
     * Regresion: con mas clientes que el limite del desplegable, "Nueva boleta"
     * y "Nuevo pago" mostraban solo los primeros sin decirlo. Ahora lo avisan,
     * y el cliente de ?cliente_id= aparece elegido aunque quede fuera.
     */
    public function testElSelectorDeClientesAvisaCuandoNoMuestraATodos(): void
    {
        $db = Database::connection();
        $this->alTerminar(static fn () => $db->exec("DELETE FROM clientes WHERE email LIKE 'zzzz-selector-http-%@example.com'"));
        $db->prepare(
            "INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais_codigo, ciudad, idioma, genero, fecha_nacimiento)
             SELECT 'ZZZZ Selector ' || lpad(g::text, 4, '0'), 'zzzz-selector-http-' || g || '@example.com', 'general',
                    CURRENT_DATE, 'AR', 'Rosario', 'Espanol', 'No especifica', DATE '1990-01-01'
             FROM generate_series(1, :cuantos) g"
        )->execute([':cuantos' => ClienteRepository::LIMITE_SELECTOR + 1]);
        $ultimo = self::contar("SELECT id FROM clientes WHERE nombre = 'ZZZZ Selector 0501'", []);

        $aviso = 'Se muestran los primeros ' . ClienteRepository::LIMITE_SELECTOR . ' clientes por nombre.';
        self::assertStringContainsString($aviso, $this->get('page=boleta-nueva')['cuerpo']);
        self::assertStringContainsString($aviso, $this->get('page=pago-nuevo')['cuerpo']);

        $conElegido = $this->get("page=boleta-nueva&cliente_id={$ultimo}")['cuerpo'];
        self::assertMatchesRegularExpression('/<option value="' . $ultimo . '"\s+selected/', $conElegido, 'el elegido se ve aunque el limite lo deje afuera');
        self::assertDoesNotMatchRegularExpression('/<option value="' . $ultimo . '"[\s>]/', $this->get('page=boleta-nueva')['cuerpo'], 'sin elegirlo, queda afuera');
    }
}
