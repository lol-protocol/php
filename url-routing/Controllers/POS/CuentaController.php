<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\OrdenRepository;
use App\Repositories\UsuarioRepository;

/**
 * Account area (/0/) — there is no login, so this is a fixed account
 * (DEFAULT_USER_ID), not a per-session one. It shows that customer's profile,
 * orders and addresses, so only the owner may open it, and this is
 * where the owner logs in: anyone else is asked for the token (401).
 */
class CuentaController extends BaseController
{
    private function usuario(): ?array
    {
        return (new UsuarioRepository($this->db()))->find(self::DEFAULT_USER_ID);
    }

    public function index(array $params = []): string
    {
        return $this->pedirPropietario(fn() => $this->conUsuario('pos/cuenta/index'));
    }

    public function perfil(array $params = []): string
    {
        return $this->pedirPropietario(fn() => $this->conUsuario('pos/cuenta/perfil'));
    }

    public function ordenes(array $params = []): string
    {
        return $this->pedirPropietario(fn() => view('pos/cuenta/ordenes', [
            'ordenes' => (new OrdenRepository($this->db()))->deUsuario(self::DEFAULT_USER_ID),
        ]));
    }

    public function deseos(array $params = []): string
    {
        return $this->pedirPropietario(fn() => view('pos/cuenta/deseos', [
            'deseos' => (new UsuarioRepository($this->db()))->deseos(self::DEFAULT_USER_ID),
        ]));
    }

    public function direcciones(array $params = []): string
    {
        return $this->pedirPropietario(fn() => view('pos/cuenta/direcciones', [
            'direcciones' => (new UsuarioRepository($this->db()))->direcciones(self::DEFAULT_USER_ID),
        ]));
    }

    public function preferencias(array $params = []): string
    {
        return $this->pedirPropietario(fn() => view('pos/cuenta/preferencias'));
    }

    /** A page that shows the account's user, or a 404 when that user doesn't exist. */
    private function conUsuario(string $view): string
    {
        $usuario = $this->usuario();
        if ($usuario === null) {
            return $this->handleNotFound();
        }

        return view($view, ['usuario' => $usuario]);
    }
}
