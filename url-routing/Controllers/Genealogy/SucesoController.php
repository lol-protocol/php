<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\SucesoRepository;

/** Suceso (event) — 9-digit id. */
class SucesoController extends BaseController
{
    public function show(array $params = []): string
    {
        $repo = new SucesoRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'genealogy/suceso/show', 'suceso',
            fn(int $id) => ['participantes' => $repo->participantes($id), 'registros' => $repo->registros($id)]);
    }
}
