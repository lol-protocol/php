<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\ColeccionRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Repositories\Genealogy\RegistroRepository;
use App\Repositories\UsuarioRepository;

/**
 * Account area (/0/) — there is no login, so this is a fixed account
 * (DEFAULT_USER_ID), not a per-session one.
 */
class CuentaController extends BaseController
{
    public function index(array $params = []): string
    {
        $usuario = (new UsuarioRepository($this->db()))->find(self::DEFAULT_USER_ID);
        if ($usuario === null) {
            return $this->handleNotFound();
        }

        return view('genealogy/cuenta/index', ['usuario' => $usuario]);
    }

    public function colecciones(array $params = []): string
    {
        return view('genealogy/cuenta/colecciones', [
            'colecciones' => (new ColeccionRepository($this->db()))->deUsuario(self::DEFAULT_USER_ID),
        ]);
    }

    public function aportes(array $params = []): string
    {
        return view('genealogy/cuenta/aportes', [
            'personas' => (new PersonaRepository($this->db()))->aportadasPor(self::DEFAULT_USER_ID),
            'registros' => (new RegistroRepository($this->db()))->aportadosPor(self::DEFAULT_USER_ID),
        ]);
    }
}
