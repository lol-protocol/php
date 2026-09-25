<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Peticion;
use App\Repositories\AuditoriaRepository;
use App\Repositories\UsuarioSistemaRepository;
use App\Validacion;
use App\View;
use PDOException;

final class UsuarioController
{
    private const PASSWORD_MINIMO = 8;

    public function index(): void
    {
        View::render('usuarios/index', [
            'usuarios' => (new UsuarioSistemaRepository())->listado(),
            'usuarioActualId' => Auth::usuarioActual()['id'] ?? null,
            'activePage' => 'usuarios',
            'titulo' => 'Usuarios',
        ]);
    }

    public function nuevo(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmar = (string) ($_POST['password_confirmar'] ?? '');

            $error = self::validarPassword($password, $confirmar);
            if ($error === null && Validacion::faltanCampos([$nombre, $email])) {
                $error = 'Completá todos los campos.';
            }

            if ($error === null) {
                try {
                    $id = Database::transaccion(static function () use ($nombre, $email, $password): int {
                        $id = (new UsuarioSistemaRepository())->crear($nombre, $email, $password);
                        AuditoriaRepository::auditarComoUsuarioActual(
                            'crear',
                            'usuario',
                            $id,
                            "Usuario #{$id}: {$nombre} ({$email})"
                        );

                        return $id;
                    });
                    header('Location: ?page=usuarios&creado=' . $id);
                    exit;
                } catch (PDOException $e) {
                    $error = Validacion::mensajeDeConflicto($e, 'usuario');
                }
            }
        }

        View::render('usuarios/nuevo', [
            'error' => $error,
            'activePage' => 'usuarios',
            'titulo' => 'Nuevo usuario',
        ]);
    }

    public function cambiarPassword(): void
    {
        $id = Peticion::id();
        $repo = new UsuarioSistemaRepository();
        $usuario = $repo->porId($id);
        if (Peticion::abortarSiNoExiste($usuario, 'Usuario no encontrado.')) {
            return;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = (string) ($_POST['password'] ?? '');
            $confirmar = (string) ($_POST['password_confirmar'] ?? '');
            $error = self::validarPassword($password, $confirmar);

            if ($error === null) {
                Database::transaccion(static function () use ($repo, $id, $password, $usuario): void {
                    $repo->cambiarPassword($id, $password);
                    AuditoriaRepository::auditarComoUsuarioActual(
                        'editar',
                        'usuario',
                        $id,
                        sprintf('Usuario #%d (%s): contraseña actualizada', $id, $usuario['email'])
                    );
                });
                header('Location: ?page=usuarios&passwordCambiada=' . $id);
                exit;
            }
        }

        View::render('usuarios/password', [
            'usuario' => $usuario,
            'error' => $error,
            'activePage' => 'usuarios',
            'titulo' => 'Cambiar contraseña',
        ]);
    }

    public function revocar(): void
    {
        $id = Peticion::id();
        $repo = new UsuarioSistemaRepository();
        $usuario = $repo->porId($id);
        if (Peticion::abortarSiNoExiste($usuario, 'Usuario no encontrado.')) {
            return;
        }

        $usuarioActualId = Auth::usuarioActual()['id'] ?? null;
        $esUnoMismo = $usuario['id'] === $usuarioActualId;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (Peticion::abortarSiConflicto($esUnoMismo, 'No podés revocar tu propio acceso.')) {
                return;
            }

            // El estado buscado viene del formulario y no de invertir el
            // actual: asi un doble clic en "Revocar" no revoca y reactiva.
            // Si el campo falta, se revoca: el valor por defecto nunca puede
            // ser dar acceso.
            $activar = ($_POST['activo'] ?? '') === '1';
            Database::transaccion(static function () use ($repo, $id, $usuario, $activar): void {
                if (!$repo->fijarActivo($id, $activar)) {
                    return;
                }
                AuditoriaRepository::auditarComoUsuarioActual(
                    $activar ? 'activar' : 'anular',
                    'usuario',
                    $id,
                    sprintf('Usuario #%d (%s): acceso %s', $id, $usuario['email'], $activar ? 'reactivado' : 'revocado')
                );
            });
            header('Location: ?page=usuarios');
            exit;
        }

        View::render('usuarios/revocar', [
            'usuario' => $usuario,
            'esUnoMismo' => $esUnoMismo,
            'activePage' => 'usuarios',
            'titulo' => $usuario['activo'] ? 'Revocar acceso' : 'Reactivar acceso',
        ]);
    }

    private static function validarPassword(string $password, string $confirmar): ?string
    {
        if ($password === '') {
            return 'Completá la contraseña.';
        }
        if (strlen($password) < self::PASSWORD_MINIMO) {
            return 'La contraseña tiene que tener al menos ' . self::PASSWORD_MINIMO . ' caracteres.';
        }
        if ($password !== $confirmar) {
            return 'Las contraseñas no coinciden.';
        }
        return null;
    }
}
