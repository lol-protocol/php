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
use App\Support\ServiceLocator;

/**
 * Home, and the listing/search page /b/{digitos}/[{texto}/][{pagina}/], where
 * digitos is the digit width of the type to list (10 = persona, 6 = grupo, ...).
 */
class HomeController extends BaseController
{
    private const POR_PAGINA = 50;

    public function index(array $params = []): string
    {
        return $this->busquedaDeFormulario() ?? view('genealogy/home/index');
    }

    public function buscar(array $params = []): string
    {
        $pedida = $this->busquedaPedida($params['ruta'] ?? []);
        if ($pedida === null) {
            return $this->handleNotFound();
        }
        [$digitos, $query, $pagina] = $pedida;
        $redirigir = $this->busquedaNoCanonica($digitos, $query, $pagina);
        if ($redirigir !== null) {
            return $redirigir;
        }

        $tipo = ServiceLocator::getInstance()->getRouter()->typeForLength($digitos);
        if ($tipo === null) {
            return $this->handleBadRequest('Tipo de listado inválido');
        }

        $offset = ($pagina - 1) * self::POR_PAGINA;
        $db = $this->db();
        $privacidad = $this->privacidad();

        $resultados = match ($tipo) {
            'persona' => (new PersonaRepository($db, $privacidad))->buscar($query, self::POR_PAGINA + 1, $offset),
            'suceso' => (new SucesoRepository($db, $privacidad))->buscar($query, self::POR_PAGINA + 1, $offset),
            'registro' => (new RegistroRepository($db, $privacidad))->buscar($query, self::POR_PAGINA + 1, $offset),
            'coleccion' => (new ColeccionRepository($db, $privacidad))
                ->buscar($query, self::POR_PAGINA + 1, $offset, incluirPrivadas: $this->esPropietario()),
            'grupo' => (new GrupoRepository($db, $privacidad))->buscar($query, self::POR_PAGINA + 1, $offset),
            'organizacion' => (new OrganizacionRepository($db, $privacidad))->buscar($query, self::POR_PAGINA + 1, $offset),
            default => null,
        };
        if ($resultados === null) {
            return $this->handleNotFound('Este tipo no tiene listado');
        }

        // One extra row tells whether a next page exists without a COUNT(*).
        $hayMas = count($resultados) > self::POR_PAGINA;

        return view('genealogy/home/listado', [
            'tipo' => $tipo,
            'digitos' => $digitos,
            'query' => $query,
            'pagina' => $pagina,
            'hayMas' => $hayMas,
            'resultados' => array_slice($resultados, 0, self::POR_PAGINA),
        ]);
    }
}
