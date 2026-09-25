<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\CatalogoRepository;

/** Grupo (category) — 4-digit id. */
class GrupoController extends BaseController
{
    public function show(array $params = []): string
    {
        $repo = new CatalogoRepository($this->db());
        return $this->renderFound($params, $repo->grupo(...), 'pos/grupo/show', 'grupo',
            fn(int $id) => ['subgrupos' => $repo->subgrupos($id), 'productos' => $repo->productosDeGrupo($id)]);
    }
}
