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
 * Home and the listing/search page: /?t=<digits>&q=<text>&p=<page>, where t
 * is the digit width of the type to list (10 = persona, 6 = grupo, ...).
 */
class HomeController extends BaseController
{
    private const POR_PAGINA = 50;

    public function index(array $params = []): string
    {
        $tipo = $this->tipoPedido();
        if ($tipo === false) {
            return view('genealogy/home/index');
        }
        if ($tipo === null) {
            return $this->handleBadRequest('Tipo de listado inválido');
        }

        $query = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 255);
        $pagina = max(1, (int)(is_string($_GET['p'] ?? null) && ctype_digit($_GET['p']) ? $_GET['p'] : 1));
        $offset = ($pagina - 1) * self::POR_PAGINA;
        $db = $this->db();

        $resultados = match ($tipo) {
            'persona' => (new PersonaRepository($db))->buscar($query, self::POR_PAGINA + 1, $offset),
            'suceso' => (new SucesoRepository($db))->buscar($query, self::POR_PAGINA + 1, $offset),
            'registro' => (new RegistroRepository($db))->buscar($query, self::POR_PAGINA + 1, $offset),
            'coleccion' => (new ColeccionRepository($db))->buscar($query, self::POR_PAGINA + 1, $offset),
            'grupo' => (new GrupoRepository($db))->buscar($query, self::POR_PAGINA + 1, $offset),
            'organizacion' => (new OrganizacionRepository($db))->buscar($query, self::POR_PAGINA + 1, $offset),
            default => null,
        };
        if ($resultados === null) {
            return $this->handleNotFound('Este tipo no tiene listado');
        }

        // One extra row tells whether a next page exists without a COUNT(*).
        $hayMas = count($resultados) > self::POR_PAGINA;

        return view('genealogy/home/listado', [
            'tipo' => $tipo,
            'digitos' => (int)$_GET['t'],
            'query' => $query,
            'pagina' => $pagina,
            'hayMas' => $hayMas,
            'resultados' => array_slice($resultados, 0, self::POR_PAGINA),
        ]);
    }

    /** @return string|null|false type name; null = invalid t; false = no t at all */
    private function tipoPedido(): string|null|false
    {
        $t = $_GET['t'] ?? null;
        if ($t === null) {
            return false;
        }
        if (!is_string($t) || !ctype_digit($t) || strlen($t) > 2) {
            return null;
        }

        return ServiceLocator::getInstance()->getRouter()->typeForLength((int)$t);
    }
}
