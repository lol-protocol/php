<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repositories\UsuarioSistemaRepository;

/**
 * Corre contra la base configurada por las env vars DB_*. Los usuarios de
 * prueba se deshacen con la transaccion de cada test (antes quedaban en la
 * tabla, desactivados, porque no hay delete() a proposito).
 */
final class UsuarioSistemaRepositoryTest extends IntegracionTestCase
{
    /** El usuario, que tiene que existir. */
    private static function usuario(UsuarioSistemaRepository $repo, int $id): array
    {
        $usuario = $repo->porId($id);
        self::assertNotNull($usuario, "el usuario #{$id} tendria que existir");

        return $usuario;
    }

    private function crearUsuarioDePrueba(UsuarioSistemaRepository $repo, string $password = 'password-original'): array
    {
        $email = 'test-usuario-' . uniqid() . '@example.com';
        return [$repo->crear('Usuario de Prueba', $email, $password), $email];
    }

    public function testCrearGuardaElHashNuncaLaContraseñaEnTextoPlano(): void
    {
        $repo = new UsuarioSistemaRepository();
        [$id] = $this->crearUsuarioDePrueba($repo, 'password-original');

        $usuario = self::usuario($repo, $id);

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
        $usuario = self::usuario($repo, $id);

        self::assertFalse(password_verify('password-original', $usuario['password_hash']));
        self::assertTrue(password_verify('password-nueva', $usuario['password_hash']));
    }

    public function testFijarActivoCambiaElEstadoYDiceSiCambioAlgo(): void
    {
        $repo = new UsuarioSistemaRepository();
        [$id] = $this->crearUsuarioDePrueba($repo);

        self::assertTrue($repo->fijarActivo($id, false));
        self::assertFalse(self::usuario($repo, $id)['activo']);

        self::assertTrue($repo->fijarActivo($id, true));
        self::assertTrue(self::usuario($repo, $id)['activo']);
    }

    /**
     * Reproduce el doble clic en "Revocar acceso": cuando era un alternar
     * (activo = NOT activo), el segundo envio del mismo formulario volvia a
     * dar acceso. Con el estado buscado explicito, el segundo no cambia nada
     * y el controller sabe que no tiene que auditar otra vez.
     */
    public function testRevocarDosVecesSeguidasNoReactivaAlUsuario(): void
    {
        $repo = new UsuarioSistemaRepository();
        [$id] = $this->crearUsuarioDePrueba($repo);

        self::assertTrue($repo->fijarActivo($id, false), 'el primer envio revoca');
        self::assertFalse($repo->fijarActivo($id, false), 'el segundo no encuentra nada que cambiar');
        self::assertFalse(self::usuario($repo, $id)['activo'], 'y el usuario sigue revocado');
    }
}
