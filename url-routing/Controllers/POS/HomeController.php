<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\CatalogoRepository;
use App\Repositories\POS\ProductoRepository;

/**
 * Home, and the listing/search page /b/{digitos}/[{texto}/][{pagina}/], where
 * digitos is the digit width of the type to list (8 = producto, 5 = atributo, ...).
 */
class HomeController extends BaseController
{
    public function index(array $params = []): string
    {
        return $this->busquedaDeFormulario() ?? view('pos/home/index');
    }

    public function buscar(array $params = []): string
    {
        // Orders have a digit width too, but they are private: never listed.
        return $this->listadoDeBusqueda(
            $params,
            'pos/home/listado',
            fn (string $tipo, string $query, int $limite, int $offset): array => $tipo === 'producto'
                ? (new ProductoRepository($this->db()))->buscar($query, $limite, $offset)
                : (new CatalogoRepository($this->db()))->buscar($tipo, $query, $limite, $offset),
            noListables: ['orden']
        );
    }
}
