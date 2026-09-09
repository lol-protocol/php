<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Auth;
use App\Repositories\IntentoLoginRepository;
use App\Repositories\UsuarioSistemaRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*.
 *
 * Solo cubre los caminos de intentarLogin() que devuelven 'invalido'/
 * 'bloqueado': bajo el SAPI de CLI (donde corre PHPUnit) session_start() no
 * deja una sesion realmente activa, asi que el camino 'ok' -que llama
 * session_regenerate_id()- dispara un warning de PHP ajeno a esta feature.
 * Los tests de LoginController/curl ya cubren el login exitoso end-to-end.
 */
final class AuthTest extends TestCase
{
    private ?int $idCreado = null;
    private ?string $email = null;

    protected function tearDown(): void
    {
        if ($this->email !== null) {
            (new IntentoLoginRepository())->limpiar($this->email);
        }
        if ($this->idCreado !== null) {
            $repo = new UsuarioSistemaRepository();
            $usuario = $repo->porId($this->idCreado);
            if ($usuario !== null && $usuario['activo']) {
                $repo->alternarActivo($this->idCreado);
            }
        }
    }

    public function testUsuarioRevocadoEsInvalidoAunConLaContraseñaCorrecta(): void
    {
        $repo = new UsuarioSistemaRepository();
        $this->email = 'test-revocado-' . uniqid() . '@example.com';
        $this->idCreado = $repo->crear('Usuario Revocado', $this->email, 'password-correcta');
        $repo->alternarActivo($this->idCreado);

        $resultado = Auth::intentarLogin($this->email, 'password-correcta');

        self::assertSame('invalido', $resultado, 'un usuario revocado debe fallar igual que una contraseña incorrecta, sin distinguirse');
    }

    public function testEmailInexistenteEsInvalido(): void
    {
        $this->email = 'no-existe-' . uniqid() . '@example.com';

        self::assertSame('invalido', Auth::intentarLogin($this->email, 'cualquier-cosa'));
    }
}
