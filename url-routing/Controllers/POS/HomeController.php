<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\CatalogoRepository;
use App\Repositories\POS\ProductoRepository;
use App\Support\ServiceLocator;

/**
 * Home and the listing/search page: /?t=<digits>&q=<text>&p=<page>, where t
 * is the digit width of the type to list (8 = producto, 5 = atributo, ...).
 */
class HomeController extends BaseController
{
    private const POR_PAGINA = 50;

    public function index(array $params = []): string
    {
        $t = $_GET['t'] ?? null;
        if ($t === null) {
            return view('pos/home/index');
        }
        if (!is_string($t) || !ctype_digit($t) || strlen($t) > 2) {
            return $this->handleBadRequest('Tipo de listado inválido');
        }

        $tipo = ServiceLocator::getInstance()->getRouter()->typeForLength((int)$t);
        if ($tipo === null) {
            return $this->handleBadRequest('Tipo de listado inválido');
        }

        $query = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 255);
        $pagina = max(1, (int)(is_string($_GET['p'] ?? null) && ctype_digit($_GET['p']) ? $_GET['p'] : 1));
        $offset = ($pagina - 1) * self::POR_PAGINA;

        $resultados = $tipo === 'producto'
            ? (new ProductoRepository($this->db()))->buscar($query, self::POR_PAGINA + 1, $offset)
            : (new CatalogoRepository($this->db()))->buscar($tipo, $query, self::POR_PAGINA + 1, $offset);

        // One extra row tells whether a next page exists without a COUNT(*).
        return view('pos/home/listado', [
            'tipo' => $tipo,
            'digitos' => (int)$t,
            'query' => $query,
            'pagina' => $pagina,
            'hayMas' => count($resultados) > self::POR_PAGINA,
            'resultados' => array_slice($resultados, 0, self::POR_PAGINA),
        ]);
    }
}
