<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\OrdenRepository;
use App\Repositories\UsuarioRepository;

/**
 * Account area (/0/) — there is no login, so this is a fixed account
 * (DEFAULT_USER_ID), not a per-session one.
 */
class CuentaController extends BaseController
{
    private function usuario(): ?array
    {
        return (new UsuarioRepository($this->db()))->find(self::DEFAULT_USER_ID);
    }

    public function index(array $params = []): string
    {
        $usuario = $this->usuario();
        if ($usuario === null) {
            return $this->handleNotFound();
        }

        return view('pos/cuenta/index', ['usuario' => $usuario]);
    }

    public function perfil(array $params = []): string
    {
        $usuario = $this->usuario();
        if ($usuario === null) {
            return $this->handleNotFound();
        }

        return view('pos/cuenta/perfil', ['usuario' => $usuario]);
    }

    public function ordenes(array $params = []): string
    {
        return view('pos/cuenta/ordenes', [
            'ordenes' => (new OrdenRepository($this->db()))->deUsuario(self::DEFAULT_USER_ID),
        ]);
    }

    public function deseos(array $params = []): string
    {
        return view('pos/cuenta/deseos', [
            'deseos' => (new UsuarioRepository($this->db()))->deseos(self::DEFAULT_USER_ID),
        ]);
    }

    public function direcciones(array $params = []): string
    {
        return view('pos/cuenta/direcciones', [
            'direcciones' => (new UsuarioRepository($this->db()))->direcciones(self::DEFAULT_USER_ID),
        ]);
    }

    public function preferencias(array $params = []): string
    {
        return view('pos/cuenta/preferencias');
    }
}
