<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use App\EnvioUnico;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\PagoRepository;

/**
 * Plata que se mueve a la vez, con peticiones HTTP de verdad contra procesos distintos del servidor y personas
 * distintas (una sesion cada una). Las pruebas de AnulableTest miran las piezas (bloquear() espera, la anulacion es
 * una sola sentencia) con una segunda conexion en un solo hilo; estas miran el flujo completo, y fallan si un
 * controlador deja de tomar el candado o de validar bajo el: quitar un bloquear() del alta de pago no rompe ninguna
 * de aquellas, y si rompe estas.
 *
 * Para que el solapamiento no dependa de la suerte, un trigger temporal duerme unos segundos al insertar en la
 * tabla que se escribe al final del flujo. El candado de la boleta se toma antes: quien lo tiene espera con el
 * dentro de esa pausa, y quien no lo tiene validaria contra datos viejos. Con el candado, el resultado es el mismo
 * sea cual sea el orden en que lleguen las peticiones, asi que estas pruebas no fallan por lentitud; solo podrian
 * dejar de detectar una falta de candado si la maquina es tan lenta que una peticion sale despues de que la otra
 * termino (las pausas dejan margen de sobra).
 *
 * Lo que crean (el servidor commitea) y el trigger se deshacen al terminar cada prueba.
 */
final class ConcurrenciaTest extends HttpTestCase
{
    private const PAIS = 'US';

    /** Lo que duerme el trigger al insertar. */
    private const PAUSA = 0.8;

    /** Un proceso por peticion simultanea: el mayor numero que lanza una prueba de esta clase es dos. */
    protected static function servidores(): int
    {
        return 2;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Un trigger que quedo de una corrida que murio frenaria todos los INSERT de esa tabla.
        self::quitarLaPausa();
        $this->alTerminar(static fn () => self::quitarLaPausa());
    }

    private static function quitarLaPausa(): void
    {
        $db = Database::connection();
        $db->exec('DROP TRIGGER IF EXISTS prueba_pausa_en_pagos ON pagos');
        $db->exec('DROP TRIGGER IF EXISTS prueba_pausa_en_notas_credito ON notas_credito');
        $db->exec('DROP FUNCTION IF EXISTS prueba_pausa()');
    }

    /** Hace que cada INSERT en $tabla (pagos o notas_credito) tarde PAUSA segundos mas, dentro de su transaccion. */
    private function pausarLosInsertEn(string $tabla): void
    {
        $db = Database::connection();
        $db->exec(
            'CREATE OR REPLACE FUNCTION prueba_pausa() RETURNS trigger LANGUAGE plpgsql AS $$
             BEGIN PERFORM pg_sleep(' . self::PAUSA . '); RETURN NEW; END $$'
        );
        $db->exec("CREATE TRIGGER prueba_pausa_en_{$tabla} BEFORE INSERT ON {$tabla} FOR EACH ROW EXECUTE FUNCTION prueba_pausa()");
    }

    /** @return array{id: int, boleta: int} un cliente y una boleta suya de $monto dolares, emitida en 2020 */
    private function clienteConBoleta(float $monto): array
    {
        $marca = strtolower(uniqid('conc'));
        $clienteId = (new ClienteRepository())->crear([
            'nombre' => 'Cliente ' . $marca, 'email' => $marca . '@example.com', 'segmento' => 'general',
            'fecha_alta' => '2020-01-01', 'pais_codigo' => self::PAIS, 'ciudad' => 'Miami', 'idioma' => 'Ingles',
            'genero' => 'No especifica', 'fecha_nacimiento' => '1990-05-05',
        ]);
        $boletaId = (new BoletaRepository())->crear([
            'cliente_id' => $clienteId, 'concepto' => 'Boleta de concurrencia', 'monto' => $monto, 'moneda_codigo' => 'USD',
            'fecha_emision' => '2020-01-01', 'fecha_vencimiento' => '2020-02-01',
        ]);
        $this->alTerminar(static function () use ($clienteId): void {
            $db = Database::connection();
            foreach (['notas_credito', 'pagos', 'boletas'] as $tabla) {
                $db->prepare("DELETE FROM {$tabla} WHERE cliente_id = :id")->execute([':id' => $clienteId]);
            }
            $db->prepare('DELETE FROM clientes WHERE id = :id')->execute([':id' => $clienteId]);
        });

        return ['id' => $clienteId, 'boleta' => $boletaId];
    }

    /**
     * Abre el formulario de nuevo pago como una persona aparte y lo deja listo para enviar.
     *
     * @return array{query: string, cookie: string, datos: array<string, scalar>}
     */
    private function pagoDeOtraPersona(array $cliente, string $monto): array
    {
        $query = 'page=pago-nuevo&cliente_id=' . $cliente['id'];
        $sesion = $this->sesionAparte($query);

        return [
            'query' => $query,
            'cookie' => $sesion['cookie'],
            'datos' => [
                'csrf_token' => self::campoOculto($sesion['cuerpo'], 'csrf_token'),
                EnvioUnico::CAMPO => self::campoOculto($sesion['cuerpo'], EnvioUnico::CAMPO),
                'cliente_id' => $cliente['id'],
                'boleta_id' => $cliente['boleta'],
                'monto' => $monto,
                'fecha_pago' => '2020-01-20',
                'metodo' => 'tarjeta',
            ],
        ];
    }

    /**
     * Abre la pantalla de anular la boleta como una persona aparte.
     *
     * @return array{query: string, cookie: string, datos: array<string, scalar>}
     */
    private function anulacionDeOtraPersona(array $cliente): array
    {
        $query = 'page=boleta-anular&id=' . $cliente['boleta'];
        $sesion = $this->sesionAparte($query);

        return ['query' => $query, 'cookie' => $sesion['cookie'], 'datos' => ['csrf_token' => self::campoOculto($sesion['cuerpo'], 'csrf_token')]];
    }

    private static function suma(string $consulta, int $boletaId): float
    {
        $stmt = Database::connection()->prepare($consulta);
        $stmt->execute([':id' => $boletaId]);

        return (float) $stmt->fetchColumn();
    }

    private static function cuantas(string $tabla, int $boletaId): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM {$tabla} WHERE boleta_id = :id");
        $stmt->execute([':id' => $boletaId]);

        return (int) $stmt->fetchColumn();
    }

    public function testDosPagosSimultaneosQueJuntosSuperanElSaldoSoloRegistranUno(): void
    {
        $cliente = $this->clienteConBoleta(100.0);
        $this->pausarLosInsertEn('pagos');
        $primero = $this->pagoDeOtraPersona($cliente, '80.00');
        $segundo = $this->pagoDeOtraPersona($cliente, '80.00');

        $respuestas = $this->enParalelo([$primero, $segundo]);

        $estados = array_column($respuestas, 'status');
        sort($estados);
        self::assertSame([200, 302], $estados, 'uno registra su pago; el otro vuelve al formulario con su error');
        $conError = $respuestas[0]['status'] === 200 ? $respuestas[0] : $respuestas[1];
        self::assertStringContainsString('El monto supera el saldo pendiente de la boleta.', html_entity_decode($conError['cuerpo']));
        self::assertSame(1, self::cuantas('pagos', $cliente['boleta']));
        self::assertEqualsWithDelta(80.0, self::suma('SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE boleta_id = :id AND NOT anulada', $cliente['boleta']), 0.001, 'no se cobro mas de lo que cabia');
    }

    /**
     * Un pago que llega mientras se anula la boleta tiene que quedar dentro de la nota de credito, o ser rechazado:
     * lo que no puede pasar es que la boleta quede anulada con plata cobrada que ninguna nota devuelve.
     */
    public function testUnPagoQueLlegaMientrasSeAnulaLaBoletaQuedaDentroDeSuNotaORechazado(): void
    {
        $cliente = $this->clienteConBoleta(100.0);
        $this->pausarLosInsertEn('pagos');
        $pago = $this->pagoDeOtraPersona($cliente, '80.00');
        $anulacion = $this->anulacionDeOtraPersona($cliente) + ['despues' => 0.3];   // cae en medio de la pausa del INSERT del pago

        $respuestas = $this->enParalelo([$pago, $anulacion]);

        self::assertSame(302, $respuestas[1]['status'], 'la anulacion termina bien, antes o despues del pago');
        self::assertTrue((bool) Database::connection()->query('SELECT anulada FROM boletas WHERE id = ' . $cliente['boleta'])->fetchColumn(), 'la boleta quedo anulada');
        $cobrado = self::suma('SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE boleta_id = :id AND NOT anulada', $cliente['boleta']);
        $devuelto = self::suma('SELECT COALESCE(SUM(monto), 0) FROM notas_credito WHERE boleta_id = :id', $cliente['boleta']);
        self::assertEqualsWithDelta($cobrado, $devuelto, 0.001, "se cobraron {$cobrado} y las notas de credito devuelven {$devuelto}");
    }

    public function testDosAnulacionesSimultaneasEmitenUnaSolaNotaDeCredito(): void
    {
        $cliente = $this->clienteConBoleta(100.0);
        (new PagoRepository())->crear([
            'boleta_id' => $cliente['boleta'], 'cliente_id' => $cliente['id'], 'monto' => 60.0, 'moneda_codigo' => 'USD',
            'fecha_pago' => '2020-01-20', 'metodo' => 'tarjeta',
        ]);
        $this->pausarLosInsertEn('notas_credito');
        $primera = $this->anulacionDeOtraPersona($cliente);
        $segunda = $this->anulacionDeOtraPersona($cliente);

        $respuestas = $this->enParalelo([$primera, $segunda]);

        self::assertSame([302, 302], array_column($respuestas, 'status'), 'la segunda no encuentra nada que anular y vuelve al listado');
        self::assertSame(1, self::cuantas('notas_credito', $cliente['boleta']), 'dos notas serian devolverle la plata dos veces');
        self::assertEqualsWithDelta(60.0, self::suma('SELECT COALESCE(SUM(monto), 0) FROM notas_credito WHERE boleta_id = :id', $cliente['boleta']), 0.001);
    }
}
