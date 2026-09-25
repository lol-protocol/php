<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\RegistroRepository;

/** Registro (documentary record) — 8-digit id. */
class RegistroController extends BaseController
{
    public function show(array $params = []): string
    {
        $repo = new RegistroRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'genealogy/registro/show', 'registro',
            fn(int $id) => ['sucesos' => $repo->sucesos($id)]);
    }

    public function fuente(array $params = []): string
    {
        $repo = new RegistroRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'genealogy/registro/fuente', 'registro');
    }
}
