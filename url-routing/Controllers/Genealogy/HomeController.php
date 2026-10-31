<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\ColeccionRepository;
use App\Repositories\Genealogy\GrupoRepository;
use App\Repositories\Genealogy\OrganizacionRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Repositories\Genealogy\RegistroRepository;
use App\Repositories\Genealogy\SucesoRepository;

/**
 * Home, and the listing/search page /b/{digitos}/[{texto}/][{pagina}/], where
 * digitos is the digit width of the type to list (10 = persona, 6 = grupo, ...).
 */
class HomeController extends BaseController
{
    /**
     * @param array<string, mixed> $params
     */
    public function index(array $params = []): string
    {
        return $this->busquedaDeFormulario() ?? view('genealogy/home/index');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function buscar(array $params = []): string
    {
        return $this->listadoDeBusqueda($params, 'genealogy/home/listado', function (string $tipo, string $query, int $limite, int $offset): ?array {
            $db = $this->db();
            $privacidad = $this->privacidad();

            return match ($tipo) {
                'persona' => (new PersonaRepository($db, $privacidad))->buscar($query, $limite, $offset),
                'suceso' => (new SucesoRepository($db, $privacidad))->buscar($query, $limite, $offset),
                'registro' => (new RegistroRepository($db, $privacidad))->buscar($query, $limite, $offset),
                'coleccion' => (new ColeccionRepository($db, $privacidad))
                    ->buscar($query, $limite, $offset, incluirPrivadas: $this->esPropietario()),
                'grupo' => (new GrupoRepository($db, $privacidad))->buscar($query, $limite, $offset),
                'organizacion' => (new OrganizacionRepository($db, $privacidad))->buscar($query, $limite, $offset),
                default => null,
            };
        });
    }
}
