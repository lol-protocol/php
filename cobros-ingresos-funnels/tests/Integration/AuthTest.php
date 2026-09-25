<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Auth;
use App\Repositories\UsuarioSistemaRepository;

/**
 * Corre contra la base configurada por las env vars DB_*.
 *
 * Solo cubre los caminos de intentarLogin() que devuelven 'invalido'/
 * 'bloqueado': bajo el SAPI de CLI (donde corre PHPUnit) session_start() no
 * deja una sesion realmente activa, asi que el camino 'ok' -que llama
 * session_regenerate_id()- dispara un warning de PHP ajeno a esta feature.
 * El login exitoso lo cubre de punta a punta la suite tests/Http.
 */
final class AuthTest extends IntegracionTestCase
{
    public function testUsuarioRevocadoEsInvalidoAunConLaContraseñaCorrecta(): void
    {
        $repo = new UsuarioSistemaRepository();
        $email = 'test-revocado-' . uniqid() . '@example.com';
        $id = $repo->crear('Usuario Revocado', $email, 'password-correcta');
        $repo->fijarActivo($id, false);

        $resultado = Auth::intentarLogin($email, 'password-correcta');

        self::assertSame('invalido', $resultado, 'un usuario revocado debe fallar igual que una contraseña incorrecta, sin distinguirse');
    }

    public function testEmailInexistenteEsInvalido(): void
    {
        self::assertSame('invalido', Auth::intentarLogin('no-existe-' . uniqid() . '@example.com', 'cualquier-cosa'));
    }

    /**
     * requerir() re-chequea esto en cada request para cortar sesiones que
     * quedaron abiertas cuando alguien les revoca el acceso. Se prueba la
     * funcion pura por separado porque requerir() en si misma llama exit().
     */
    public function testSesionSigueValidaConUsuarioActivo(): void
    {
        self::assertTrue(Auth::sesionSigueValida(['id' => 1, 'activo' => true]));
    }

    public function testSesionSigueValidaEsFalsaSiElUsuarioFueRevocado(): void
    {
        self::assertFalse(Auth::sesionSigueValida(['id' => 1, 'activo' => false]));
    }

    public function testSesionSigueValidaEsFalsaSiElUsuarioYaNoExiste(): void
    {
        self::assertFalse(Auth::sesionSigueValida(null));
    }
}
