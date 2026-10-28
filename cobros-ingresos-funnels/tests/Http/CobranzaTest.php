<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use DateTimeImmutable;

/**
 * La pantalla de Cobranza contra la app levantada. Los clientes y boletas que crean
 * (el servidor commitea) se borran al terminar.
 */
final class CobranzaTest extends HttpTestCase
{
    private const PAIS = 'US';

    /**
     * Un cliente de prueba con una boleta abierta de un monto enorme (para que caiga en la
     * primera pagina de la lista, que ordena por lo vencido), que vence dentro de
     * $diasHastaElVencimiento dias (negativo = ya vencio). Se borra todo al terminar.
     *
     * @return array{id: int, nombre: string, email: string}
     */
    private function clienteConDeuda(int $diasHastaElVencimiento): array
    {
        $nombre = 'Cobranza HTTP ' . uniqid();
        $email = 'cobranza-http-' . uniqid() . '@example.com';
        $id = (new ClienteRepository())->crear([
            'nombre' => $nombre, 'email' => $email, 'segmento' => 'general', 'fecha_alta' => '2020-01-01',
            'pais_codigo' => self::PAIS, 'ciudad' => 'Miami', 'idioma' => 'Ingles', 'genero' => 'No especifica',
            'fecha_nacimiento' => '1990-05-05',
        ]);
        $this->alTerminar(static function () use ($id): void {
            $db = Database::connection();
            $db->prepare('DELETE FROM boletas WHERE cliente_id = :id')->execute([':id' => $id]);
            $db->prepare('DELETE FROM clientes WHERE id = :id')->execute([':id' => $id]);
        });

        $moneda = Database::connection()->query("SELECT moneda_codigo FROM paises WHERE codigo = '" . self::PAIS . "'")->fetchColumn();
        (new BoletaRepository())->crear([
            'cliente_id' => $id, 'concepto' => 'Boleta de cobranza HTTP', 'monto' => 99_999_999,
            'moneda_codigo' => $moneda, 'fecha_emision' => '2020-01-01',
            'fecha_vencimiento' => (new DateTimeImmutable(sprintf('%+d days', $diasHastaElVencimiento)))->format('Y-m-d'),
        ]);

        return ['id' => $id, 'nombre' => $nombre, 'email' => $email];
    }

    public function testLaPantallaListaAlClienteConSuEmailSusDiasDeAtrasoYSusAcciones(): void
    {
        $cliente = $this->clienteConDeuda(-45);

        $pantalla = $this->get('page=cobranza');

        $this->assertStatus(200, $pantalla);
        self::assertStringContainsString($cliente['nombre'], $pantalla['cuerpo']);
        self::assertStringContainsString('href="mailto:' . $cliente['email'] . '"', $pantalla['cuerpo'], 'el email se puede usar para reclamar');
        self::assertStringContainsString('45 días', $pantalla['cuerpo']);
        self::assertStringContainsString("page=cliente&id={$cliente['id']}", $pantalla['cuerpo'], 'lleva a la ficha');
        self::assertStringContainsString("page=pago-nuevo&amp;cliente_id={$cliente['id']}", $pantalla['cuerpo'], 'y a registrar su pago');
        self::assertStringContainsString('Saldo abierto (USD)', $pantalla['cuerpo']);
    }

    public function testElAlcanceDeLaUrlDecideSiSeVeAlQueTodaviaNoVence(): void
    {
        $alDia = $this->clienteConDeuda(30);

        self::assertStringNotContainsString($alDia['nombre'], $this->get('page=cobranza')['cuerpo'], 'por defecto, solo con saldo vencido');
        self::assertStringNotContainsString($alDia['nombre'], $this->get('page=cobranza&alcance=vencidos')['cuerpo']);
        $todos = $this->get('page=cobranza&alcance=todos&orden=saldo');
        self::assertStringContainsString($alDia['nombre'], $todos['cuerpo'], 'en "todo el saldo abierto" si');
        self::assertStringContainsString('value="todos" selected', $todos['cuerpo'], 'el selector refleja el filtro');
        self::assertStringContainsString('value="saldo" selected', $todos['cuerpo'], 'y el orden');
        self::assertStringContainsString('Al día', $todos['cuerpo']);
    }

    public function testUnAlcanceOUnOrdenInventadosAvisanYUsanElPorDefecto(): void
    {
        $cliente = $this->clienteConDeuda(-10);

        $alcance = $this->get('page=cobranza&alcance=inventado');
        $this->assertStatus(200, $alcance);
        self::assertStringContainsString('El alcance pedido no existe; se muestran los clientes con saldo vencido.', $alcance['cuerpo']);
        self::assertStringContainsString($cliente['nombre'], $alcance['cuerpo']);

        $orden = $this->get('page=cobranza&orden=inventado');
        $this->assertStatus(200, $orden);
        self::assertStringContainsString('El orden pedido no existe; se ordena por mayor monto vencido.', $orden['cuerpo']);
        self::assertStringContainsString('value="vencido" selected', $orden['cuerpo']);

        self::assertStringNotContainsString('no existe', $this->get('page=cobranza&alcance=todos&orden=cliente')['cuerpo'], 'lo valido no avisa nada');
    }

    public function testElDashboardYElMenuLlevanALaCobranza(): void
    {
        $dashboard = $this->get('page=dashboard')['cuerpo'];

        self::assertStringContainsString('<a href="?page=cobranza">Cobranza</a>', str_replace(' class=""', '', $dashboard), 'esta en el menu');
        self::assertStringContainsString('Ver quién debe', $dashboard, 'la tarjeta de cartera lleva a la lista');
    }
}
