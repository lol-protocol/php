<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\CatalogoRepository;

/** Coleccion (collection / campaign) — 7-digit id. */
class ColeccionController extends BaseController
{
    public function show(array $params = []): string
    {
        $repo = new CatalogoRepository($this->db());
        return $this->renderFound($params, $repo->coleccion(...), 'pos/coleccion/show', 'coleccion',
            fn(int $id) => ['productos' => $repo->productosDeColeccion($id)]);
    }
}
