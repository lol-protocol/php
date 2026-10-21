<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\ColeccionRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Repositories\Genealogy\RegistroRepository;
use App\Repositories\UsuarioRepository;

/**
 * Account area (/0/) — there are no user accounts, so this is a fixed account
 * (DEFAULT_USER_ID), not a per-session one. It shows that user's e-mail and
 * contributions, so only the owner may open it, and this is
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
        return $this->pedirPropietario(fn() => $this->conUsuario('genealogy/cuenta/index'));
    }

    public function colecciones(array $params = []): string
    {
        return $this->pedirPropietario(fn() => view('genealogy/cuenta/colecciones', [
            'colecciones' => (new ColeccionRepository($this->db(), $this->privacidad()))->deUsuario(self::DEFAULT_USER_ID),
        ]));
    }

    public function aportes(array $params = []): string
    {
        return $this->pedirPropietario(fn() => view('genealogy/cuenta/aportes', [
            'personas' => (new PersonaRepository($this->db(), $this->privacidad()))->aportadasPor(self::DEFAULT_USER_ID),
            'registros' => (new RegistroRepository($this->db(), $this->privacidad()))->aportadosPor(self::DEFAULT_USER_ID),
        ]));
    }

    /** A page that shows the account's user, or a 404 when that user doesn't exist. */
    private function conUsuario(string $view): string
    {
        $usuario = $this->usuario();
        if ($usuario === null) {
            return $this->handleNotFound($this->sinUsuario());
        }

        return view($view, ['usuario' => $usuario]);
    }
}
