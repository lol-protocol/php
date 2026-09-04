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
            if (Auth::intentarLogin($email, $password)) {
                header('Location: ' . $next);
                exit;
            }
            $error = 'Email o contraseña incorrectos.';
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
