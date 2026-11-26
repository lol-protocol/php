<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use App\EnvioUnico;

/**
 * Migracion 021: la fecha de nacimiento que se escribe en el alta se usa para comprobar la mayoria de edad y se descarta; la ficha
 * muestra el rango de edad y la fecha no aparece en ningun lado. El alta es de verdad (queda commiteada): se borra al terminar.
 */
final class RangoDeEdadPantallaTest extends HttpTestCase
{
    public function testElAltaGuardaElRangoYLaFichaNoMuestraLaFecha(): void
    {
        $nacimiento = date('Y-m-d', strtotime('-30 years -40 days'));
        $formulario = $this->get('page=cliente-nuevo')['cuerpo'];
        self::assertStringContainsString('no se guarda</strong>', $formulario, 'el formulario avisa que la fecha no se guarda');

        $email = 'rango-' . uniqid() . '@example.com';
        $this->alTerminar(static function () use ($email): void {
            $db = Database::connection();
            $db->prepare("DELETE FROM auditoria WHERE entidad = 'cliente' AND entidad_id IN (SELECT id FROM clientes WHERE email = :e)")->execute([':e' => $email]);
            $db->prepare('DELETE FROM clientes WHERE email = :e')->execute([':e' => $email]);
        });
        $datos = [
            'csrf_token' => self::campoOculto($formulario, 'csrf_token'),
            EnvioUnico::CAMPO => self::campoOculto($formulario, EnvioUnico::CAMPO),
            'nombre' => 'Cliente del rango', 'email' => $email, 'pais_codigo' => 'AR', 'ciudad' => 'Rosario', 'idioma' => 'Espanol',
            'genero' => 'No especifica', 'fecha_nacimiento' => $nacimiento, 'sin_restricciones' => '1', 'segmento' => 'general',
        ];
        $token = $datos[EnvioUnico::CAMPO];
        $this->alTerminar(static fn () => Database::connection()
            ->prepare('DELETE FROM envios_formulario WHERE token = :token')
            ->execute([':token' => $token]));

        $alta = $this->post('page=cliente-nuevo', $datos);
        $this->assertStatus(302, $alta);

        self::assertSame('25-34', Database::connection()->query("SELECT rango_de_edad FROM clientes WHERE email = '{$email}'")->fetchColumn());

        if (preg_match('/^Location: (\S+)/mi', $alta['cabeceras'], $m) !== 1) {
            self::fail('el alta tenia que redirigir a la ficha');
        }
        $ficha = $this->get(ltrim($m[1], '/?'))['cuerpo'];
        self::assertStringContainsString('Rango de edad (al alta)', $ficha);
        self::assertMatchesRegularExpression('~Rango de edad \(al alta\)</span><span class="valor">25-34</span>~', $ficha);
        self::assertStringNotContainsString($nacimiento, $ficha, 'la fecha no esta en la ficha');
        self::assertStringNotContainsString('años</span>', $ficha, 'ya no se muestra una edad exacta');

        $auditoria = Database::connection()->query("SELECT string_agg(detalle, ' ') FROM auditoria WHERE entidad = 'cliente' AND detalle LIKE '%{$email}%'")->fetchColumn();
        self::assertStringNotContainsString($nacimiento, (string) $auditoria, 'la fecha tampoco queda en la auditoria');
    }
}
