<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\PersonaRepository;

/** Persona — 10-digit id. */
class PersonaController extends BaseController
{
    private function repo(): PersonaRepository
    {
        return new PersonaRepository($this->db());
    }

    public function show(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/show', 'persona',
            fn(int $id) => ['vinculos' => $repo->vinculos($id), 'cronologia' => $repo->cronologia($id)]);
    }

    public function ascendencia(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/ascendencia', 'persona',
            fn(int $id) => ['ancestros' => $repo->ascendencia($id)]);
    }

    public function descendencia(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/descendencia', 'persona',
            fn(int $id) => ['descendientes' => $repo->descendencia($id)]);
    }

    public function vinculos(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/vinculos', 'persona',
            fn(int $id) => ['vinculos' => $repo->vinculos($id)]);
    }

    public function cronologia(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/cronologia', 'persona',
            fn(int $id) => ['eventos' => $repo->cronologia($id)]);
    }
}
