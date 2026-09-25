<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\CatalogoRepository;

/** Atributo (color, size, material) — 5-digit id. */
class AtributoController extends BaseController
{
    public function show(array $params = []): string
    {
        $repo = new CatalogoRepository($this->db());
        return $this->renderFound($params, $repo->atributo(...), 'pos/atributo/show', 'atributo');
    }

    public function productos(array $params = []): string
    {
        $repo = new CatalogoRepository($this->db());
        return $this->renderFound($params, $repo->atributo(...), 'pos/atributo/productos', 'atributo',
            fn(int $id) => ['productos' => $repo->productosConAtributo($id)]);
    }
}
