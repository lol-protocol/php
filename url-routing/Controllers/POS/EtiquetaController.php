<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\CatalogoRepository;

/** Etiqueta (tag) — 6-digit id. */
class EtiquetaController extends BaseController
{
    public function show(array $params = []): string
    {
        $repo = new CatalogoRepository($this->db());
        return $this->renderFound($params, $repo->etiqueta(...), 'pos/etiqueta/show', 'etiqueta');
    }

    public function productos(array $params = []): string
    {
        $repo = new CatalogoRepository($this->db());
        return $this->renderFound($params, $repo->etiqueta(...), 'pos/etiqueta/productos', 'etiqueta',
            fn(int $id) => ['productos' => $repo->productosConEtiqueta($id)]);
    }
}
