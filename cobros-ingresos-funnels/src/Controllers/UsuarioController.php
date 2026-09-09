<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Repositories\AuditoriaRepository;
use App\Repositories\UsuarioSistemaRepository;
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
            if ($error === null && ($nombre === '' || $email === '')) {
                $error = 'Completá todos los campos.';
            }

            if ($error === null) {
                try {
                    $id = (new UsuarioSistemaRepository())->crear($nombre, $email, $password);
                    AuditoriaRepository::auditarComoUsuarioActual(
                        'crear',
                        'usuario',
                        $id,
                        "Usuario #{$id}: {$nombre} ({$email})"
                    );
                    header('Location: ?page=usuarios&creado=' . $id);
                    exit;
                } catch (PDOException $e) {
                    $error = str_contains($e->getMessage(), 'unique')
                        ? 'Ya existe un usuario con ese email.'
                        : 'No se pudo crear el usuario.';
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
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $repo = new UsuarioSistemaRepository();
        $usuario = $repo->porId($id);
        if ($usuario === null) {
            http_response_code(404);
            echo 'Usuario no encontrado.';
            return;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = (string) ($_POST['password'] ?? '');
            $confirmar = (string) ($_POST['password_confirmar'] ?? '');
            $error = self::validarPassword($password, $confirmar);

            if ($error === null) {
                $repo->cambiarPassword($id, $password);
                AuditoriaRepository::auditarComoUsuarioActual(
                    'editar',
                    'usuario',
                    $id,
                    sprintf('Usuario #%d (%s): contraseña actualizada', $id, $usuario['email'])
                );
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
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $repo = new UsuarioSistemaRepository();
        $usuario = $repo->porId($id);
        if ($usuario === null) {
            http_response_code(404);
            echo 'Usuario no encontrado.';
            return;
        }

        $usuarioActualId = Auth::usuarioActual()['id'] ?? null;
        $esUnoMismo = $usuario['id'] === $usuarioActualId;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($esUnoMismo) {
                http_response_code(409);
                echo 'No podés revocar tu propio acceso.';
                return;
            }

            $nuevoEstado = $repo->alternarActivo($id);
            AuditoriaRepository::auditarComoUsuarioActual(
                $nuevoEstado ? 'activar' : 'anular',
                'usuario',
                $id,
                sprintf('Usuario #%d (%s): acceso %s', $id, $usuario['email'], $nuevoEstado ? 'reactivado' : 'revocado')
            );
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
