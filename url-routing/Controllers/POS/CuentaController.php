<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\OrdenRepository;
use App\Repositories\UsuarioRepository;

/** Account area (/0/) — everything is scoped to the logged-in user. */
class CuentaController extends BaseController
{
    private function usuario(): ?array
    {
        return (new UsuarioRepository($this->db()))->find($this->getCurrentUserId());
    }

    public function index(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tu cuenta');
        }
        $usuario = $this->usuario();
        if ($usuario === null) {
            return $this->handleUnauthorized('La sesión no corresponde a ningún usuario');
        }

        return view('pos/cuenta/index', ['usuario' => $usuario]);
    }

    public function perfil(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tu perfil');
        }
        $usuario = $this->usuario();
        if ($usuario === null) {
            return $this->handleUnauthorized('La sesión no corresponde a ningún usuario');
        }

        return view('pos/cuenta/perfil', ['usuario' => $usuario]);
    }

    public function ordenes(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tus órdenes');
        }

        return view('pos/cuenta/ordenes', [
            'ordenes' => (new OrdenRepository($this->db()))->deUsuario($this->getCurrentUserId()),
        ]);
    }

    public function deseos(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tu lista de deseos');
        }

        return view('pos/cuenta/deseos', [
            'deseos' => (new UsuarioRepository($this->db()))->deseos($this->getCurrentUserId()),
        ]);
    }

    public function direcciones(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tus direcciones');
        }

        return view('pos/cuenta/direcciones', [
            'direcciones' => (new UsuarioRepository($this->db()))->direcciones($this->getCurrentUserId()),
        ]);
    }

    public function preferencias(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tus preferencias');
        }

        return view('pos/cuenta/preferencias');
    }
}
