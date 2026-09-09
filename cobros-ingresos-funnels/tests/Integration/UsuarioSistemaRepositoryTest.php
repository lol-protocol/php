<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repositories\UsuarioSistemaRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. No hay delete() a
 * proposito (el acceso se revoca via `activo`, no se borra el usuario), asi
 * que estos tests dejan a sus usuarios de prueba desactivados en el tearDown
 * en vez de borrarlos.
 */
final class UsuarioSistemaRepositoryTest extends TestCase
{
    private ?int $idCreado = null;

    protected function tearDown(): void
    {
        if ($this->idCreado === null) {
            return;
        }
        $repo = new UsuarioSistemaRepository();
        $usuario = $repo->porId($this->idCreado);
        if ($usuario !== null && $usuario['activo']) {
            $repo->alternarActivo($this->idCreado);
        }
    }

    private function crearUsuarioDePrueba(UsuarioSistemaRepository $repo, string $password = 'password-original'): array
    {
        $email = 'test-usuario-' . uniqid() . '@example.com';
        $this->idCreado = $repo->crear('Usuario de Prueba', $email, $password);
        return [$this->idCreado, $email];
    }

    public function testCrearGuardaElHashNuncaLaContraseñaEnTextoPlano(): void
    {
        $repo = new UsuarioSistemaRepository();
        [$id] = $this->crearUsuarioDePrueba($repo, 'password-original');

        $usuario = $repo->porId($id);

        self::assertNotNull($usuario);
        self::assertTrue($usuario['activo'], 'un usuario nuevo arranca activo');
        self::assertNotSame('password-original', $usuario['password_hash']);
        self::assertTrue(password_verify('password-original', $usuario['password_hash']));
    }

    public function testListadoIncluyeAlUsuarioRecienCreadoYNoExponeElHash(): void
    {
        $repo = new UsuarioSistemaRepository();
        [$id] = $this->crearUsuarioDePrueba($repo);

        $fila = null;
        foreach ($repo->listado() as $u) {
            if ($u['id'] === $id) {
                $fila = $u;
                break;
            }
        }

        self::assertNotNull($fila, 'el usuario recien creado debe aparecer en el listado');
        self::assertArrayNotHasKey('password_hash', $fila, 'el listado no debe traer el hash de la contraseña');
    }

    public function testCambiarPasswordActualizaElHash(): void
    {
        $repo = new UsuarioSistemaRepository();
        [$id] = $this->crearUsuarioDePrueba($repo, 'password-original');

        $repo->cambiarPassword($id, 'password-nueva');
        $usuario = $repo->porId($id);

        self::assertFalse(password_verify('password-original', $usuario['password_hash']));
        self::assertTrue(password_verify('password-nueva', $usuario['password_hash']));
    }

    public function testAlternarActivoInvierteElEstadoYDevuelveElNuevoValor(): void
    {
        $repo = new UsuarioSistemaRepository();
        [$id] = $this->crearUsuarioDePrueba($repo);

        $nuevoEstado = $repo->alternarActivo($id);
        self::assertFalse($nuevoEstado);
        self::assertFalse($repo->porId($id)['activo']);

        $otraVez = $repo->alternarActivo($id);
        self::assertTrue($otraVez);
        self::assertTrue($repo->porId($id)['activo']);
    }
}
