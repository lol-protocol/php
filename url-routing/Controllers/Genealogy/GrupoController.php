<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\GrupoRepository;

/** Grupo (surname) — 6-digit id. */
class GrupoController extends BaseController
{
    /**
     * @param array<string, mixed> $params
     */
    public function show(array $params = []): string
    {
        $repo = new GrupoRepository($this->db(), $this->privacidad());
        return $this->renderFound($params, $repo->find(...), 'genealogy/grupo/show', 'grupo',
            fn(int $id) => ['dispersion' => $repo->dispersion($id)], self::movida('grupo', $repo->aliasDe(...), null));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function red(array $params = []): string
    {
        $repo = new GrupoRepository($this->db(), $this->privacidad());
        return $this->renderFound($params, $repo->find(...), 'genealogy/grupo/red', 'grupo',
            fn(int $id) => ['personas' => $repo->red($id)], self::movida('grupo', $repo->aliasDe(...), 1));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function dispersion(array $params = []): string
    {
        $repo = new GrupoRepository($this->db(), $this->privacidad());
        return $this->renderFound($params, $repo->find(...), 'genealogy/grupo/dispersion', 'grupo',
            fn(int $id) => ['dispersion' => $repo->dispersion($id)], self::movida('grupo', $repo->aliasDe(...), 2));
    }
}
