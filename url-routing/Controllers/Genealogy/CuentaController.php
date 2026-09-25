<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\ColeccionRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Repositories\Genealogy\RegistroRepository;
use App\Repositories\UsuarioRepository;

/** Account area (/0/) — everything is scoped to the logged-in user. */
class CuentaController extends BaseController
{
    public function index(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tu cuenta');
        }

        $usuario = (new UsuarioRepository($this->db()))->find($this->getCurrentUserId());
        if ($usuario === null) {
            return $this->handleUnauthorized('La sesión no corresponde a ningún usuario');
        }

        return view('genealogy/cuenta/index', ['usuario' => $usuario]);
    }

    public function colecciones(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tus colecciones');
        }

        return view('genealogy/cuenta/colecciones', [
            'colecciones' => (new ColeccionRepository($this->db()))->deUsuario($this->getCurrentUserId()),
        ]);
    }

    public function aportes(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tus aportes');
        }

        $usuarioId = $this->getCurrentUserId();
        return view('genealogy/cuenta/aportes', [
            'personas' => (new PersonaRepository($this->db()))->aportadasPor($usuarioId),
            'registros' => (new RegistroRepository($this->db()))->aportadosPor($usuarioId),
        ]);
    }
}
