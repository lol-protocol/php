<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\CatalogoRepository;
use App\Repositories\POS\ProductoRepository;
use App\Support\ServiceLocator;

/**
 * Home, and the listing/search page /b/{digitos}/[{texto}/][{pagina}/], where
 * digitos is the digit width of the type to list (8 = producto, 5 = atributo, ...).
 */
class HomeController extends BaseController
{
    private const POR_PAGINA = 50;

    public function index(array $params = []): string
    {
        return $this->busquedaDeFormulario() ?? view('pos/home/index');
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
        // Orders have a digit width too, but they are private: never listed.
        if ($tipo === null || $tipo === 'orden') {
            return $this->handleBadRequest('Tipo de listado inválido');
        }

        $offset = ($pagina - 1) * self::POR_PAGINA;

        $resultados = $tipo === 'producto'
            ? (new ProductoRepository($this->db()))->buscar($query, self::POR_PAGINA + 1, $offset)
            : (new CatalogoRepository($this->db()))->buscar($tipo, $query, self::POR_PAGINA + 1, $offset);

        // One extra row tells whether a next page exists without a COUNT(*).
        return view('pos/home/listado', [
            'tipo' => $tipo,
            'digitos' => $digitos,
            'query' => $query,
            'pagina' => $pagina,
            'hayMas' => count($resultados) > self::POR_PAGINA,
            'resultados' => array_slice($resultados, 0, self::POR_PAGINA),
        ]);
    }
}
