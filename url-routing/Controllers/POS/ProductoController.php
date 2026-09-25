<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\ProductoRepository;

/** Producto — 8-digit id. */
class ProductoController extends BaseController
{
    public function show(array $params = []): string
    {
        $repo = new ProductoRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'pos/producto/show', 'producto',
            fn(int $id) => ['variantes' => $repo->variantes($id), 'etiquetas' => $repo->etiquetas($id)]);
    }

    public function variantes(array $params = []): string
    {
        $repo = new ProductoRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'pos/producto/variantes', 'producto',
            fn(int $id) => ['variantes' => $repo->variantes($id)]);
    }

    public function atributos(array $params = []): string
    {
        $repo = new ProductoRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'pos/producto/atributos', 'producto',
            fn(int $id) => ['atributos' => $repo->atributos($id)]);
    }
}
