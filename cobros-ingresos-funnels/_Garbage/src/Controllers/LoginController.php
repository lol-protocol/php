<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\View;

final class LoginController
{
    public function index(): void
    {
        Auth::iniciar();
        $next = Auth::destinoSeguro($_GET['next'] ?? null);

        if (Auth::autenticado()) {
            header('Location: ' . $next);
            exit;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $resultado = Auth::intentarLogin($email, $password);

            if ($resultado === 'ok') {
                header('Location: ' . $next);
                exit;
            }

            $error = match ($resultado) {
                'bloqueado' => 'Demasiados intentos fallidos. Probá de nuevo en ' . Auth::minutosDeBloqueo($email) . ' minuto(s).',
                default => 'Email o contraseña incorrectos.',
            };
        }

        View::render('login', [
            'error' => $error,
            'next' => $next,
            'activePage' => 'login',
            'titulo' => 'Ingresar',
        ], sinLayout: true);
    }

    public function salir(): void
    {
        Auth::logout();
        header('Location: ?page=login');
        exit;
    }
}
