<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\OrganizacionRepository;

/** Organizacion (archive, parish...) — 5-digit id. */
class OrganizacionController extends BaseController
{
    public function show(array $params = []): string
    {
        $repo = new OrganizacionRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'genealogy/organizacion/show', 'organizacion');
    }

    public function miembros(array $params = []): string
    {
        $repo = new OrganizacionRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'genealogy/organizacion/miembros', 'organizacion',
            fn(int $id) => ['miembros' => $repo->miembros($id)]);
    }

    public function registros(array $params = []): string
    {
        $repo = new OrganizacionRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'genealogy/organizacion/registros', 'organizacion',
            fn(int $id) => ['registros' => $repo->registros($id)]);
    }
}
