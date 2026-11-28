<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use App\Repositories\ClienteRepository;

/**
 * Migracion 022 por pantalla: la ficha ofrece anonimizar, pide escribir el email, deja el cliente sin nombre/email/ciudad con su plazo para
 * deshacer, y deshacer lo devuelve. El cliente es de verdad (queda commiteado): se borra al terminar.
 */
final class AnonimizarClientePantallaTest extends HttpTestCase
{
    private int $id;
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->email = 'anonimizar-http-' . uniqid() . '@example.com';
        $this->id = (new ClienteRepository())->crear([
            'nombre' => 'Zoe Anonimizable', 'email' => $this->email, 'segmento' => 'general', 'fecha_alta' => date('Y-m-d'), 'pais_codigo' => 'AR',
            'ciudad' => 'Rosario', 'idioma' => 'Espanol', 'genero' => 'No especifica', 'fecha_nacimiento' => date('Y-m-d', strtotime('-33 years')),
        ]);
        $id = $this->id;
        $this->alTerminar(static function () use ($id): void {
            $db = Database::connection();
            $db->exec("DELETE FROM auditoria WHERE entidad = 'cliente' AND entidad_id = {$id}");
            $db->exec("DELETE FROM clientes WHERE id = {$id}");   // datos_anonimizados cae en cascada
        });
    }

    /** El token CSRF de la pagina $query: el formulario trae su propio campo. */
    private function token(string $query): string
    {
        return self::campoOculto($this->get($query)['cuerpo'], 'csrf_token');
    }

    private function estaAnonimizado(): bool
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM clientes WHERE id = {$this->id} AND anonimizado_en IS NOT NULL")->fetchColumn() === 1;
    }

    public function testLaFichaOfreceAnonimizarYLaPantallaExplicaQueSeBorra(): void
    {
        $ficha = $this->get("page=cliente&id={$this->id}")['cuerpo'];
        self::assertStringContainsString("?page=cliente-anonimizar&amp;id={$this->id}", $ficha);

        $pantalla = $this->get("page=cliente-anonimizar&id={$this->id}")['cuerpo'];
        self::assertStringContainsString('borrar el nombre, el email y la ciudad', $pantalla);
        self::assertStringContainsString('deshacer durante 7 días', $pantalla);
        self::assertStringContainsString('name="confirmar_email"', $pantalla);
    }

    public function testConUnEmailEquivocadoNoSeAnonimiza(): void
    {
        $respuesta = $this->post("page=cliente-anonimizar&id={$this->id}", [
            'csrf_token' => $this->token("page=cliente-anonimizar&id={$this->id}"), 'confirmar_email' => 'otro@example.com',
        ]);

        $this->assertStatus(200, $respuesta);
        self::assertStringContainsString('no se anonimizó', $respuesta['cuerpo']);
        self::assertFalse($this->estaAnonimizado());
    }

    public function testAnonimizarYDeshacerPorPantalla(): void
    {
        // El email se compara sin mirar mayusculas.
        $anonimizar = $this->post("page=cliente-anonimizar&id={$this->id}", [
            'csrf_token' => $this->token("page=cliente-anonimizar&id={$this->id}"), 'confirmar_email' => strtoupper($this->email),
        ]);
        $this->assertStatus(302, $anonimizar);
        self::assertStringContainsString("anonimizado={$this->id}", $anonimizar['cabeceras']);
        self::assertTrue($this->estaAnonimizado());

        $ficha = $this->get("page=cliente&id={$this->id}&anonimizado={$this->id}")['cuerpo'];
        self::assertStringContainsString('anonimizado: ya no tiene nombre, email ni ciudad', $ficha);
        self::assertStringContainsString("Cliente anonimizado #{$this->id}", $ficha);
        self::assertStringNotContainsString('Zoe Anonimizable', $ficha);
        self::assertStringNotContainsString($this->email, $ficha);
        self::assertStringNotContainsString("page=boleta-nueva&amp;cliente_id={$this->id}", $ficha, 'ya no se le ofrecen boletas');
        self::assertStringContainsString('Deshacer la anonimización', $ficha);
        self::assertStringNotContainsString('Zoe Anonimizable', $this->get('page=clientes&q=anonimizado')['cuerpo'] . $this->get('page=auditoria')['cuerpo']);

        // Anonimizar de nuevo manda a la ficha, sin tocar nada.
        $otra = $this->get("page=cliente-anonimizar&id={$this->id}");
        $this->assertStatus(302, $otra);

        $deshacer = $this->post("page=cliente-deshacer-anonimizacion&id={$this->id}", ['csrf_token' => self::campoOculto($ficha, 'csrf_token')]);
        $this->assertStatus(302, $deshacer);
        self::assertStringContainsString("restaurado={$this->id}", $deshacer['cabeceras']);
        self::assertFalse($this->estaAnonimizado());

        $restaurada = $this->get("page=cliente&id={$this->id}&restaurado={$this->id}")['cuerpo'];
        self::assertStringContainsString('Zoe Anonimizable', $restaurada);
        self::assertStringContainsString($this->email, $restaurada);
        self::assertStringContainsString('restaurado: volvieron su nombre, email y ciudad', $restaurada);
    }

    public function testDeshacerVencidoAvisaYNoCambiaNada(): void
    {
        $this->post("page=cliente-anonimizar&id={$this->id}", [
            'csrf_token' => $this->token("page=cliente-anonimizar&id={$this->id}"), 'confirmar_email' => $this->email,
        ]);
        Database::connection()->exec("UPDATE datos_anonimizados SET vence_en = now() - interval '1 minute' WHERE cliente_id = {$this->id}");

        $ficha = $this->get("page=cliente&id={$this->id}")['cuerpo'];
        self::assertStringContainsString('Ya no se puede deshacer', $ficha);
        self::assertStringNotContainsString('Deshacer la anonimización', $ficha);

        $deshacer = $this->post("page=cliente-deshacer-anonimizacion&id={$this->id}", ['csrf_token' => $this->token('page=cliente-nuevo')]);
        self::assertStringContainsString("sin_restaurar={$this->id}", $deshacer['cabeceras']);
        self::assertTrue($this->estaAnonimizado());
        self::assertStringContainsString('No se pudo deshacer la anonimización', $this->get("page=cliente&id={$this->id}&sin_restaurar={$this->id}")['cuerpo']);
    }

    public function testElComandoDePurgaBorraLosVencidos(): void
    {
        $this->post("page=cliente-anonimizar&id={$this->id}", [
            'csrf_token' => $this->token("page=cliente-anonimizar&id={$this->id}"), 'confirmar_email' => $this->email,
        ]);
        Database::connection()->exec("UPDATE datos_anonimizados SET vence_en = now() - interval '1 minute' WHERE cliente_id = {$this->id}");

        $salida = (string) shell_exec('APP_ENV=dev php ' . escapeshellarg(__DIR__ . '/../../bin/purgar_anonimizados.php') . ' 2>&1');

        self::assertStringContainsString('Purgados los datos originales', $salida);
        self::assertSame(0, (int) Database::connection()->query("SELECT COUNT(*) FROM datos_anonimizados WHERE cliente_id = {$this->id}")->fetchColumn());
    }
}
