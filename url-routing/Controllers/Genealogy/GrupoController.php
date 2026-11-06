<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\GrupoRepository;

/** Grupo (surname) — 6-digit id. */
class GrupoController extends BaseController
{
    /**
     * A merged-away id (grupo_alias) leads to the same page of the grupo it was merged into: $accion is that
     * page's action code, null for the grupo's own page.
     *
     * @return callable(int): ?string
     */
    private static function movida(GrupoRepository $repo, ?int $accion): callable
    {
        return static function (int $id) use ($repo, $accion): ?string {
            $destino = $repo->aliasDe($id);
            if ($destino === null) {
                return null;
            }

            return $accion === null ? enlace('grupo', $destino) : accion('grupo', $destino, $accion);
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    public function show(array $params = []): string
    {
        $repo = new GrupoRepository($this->db(), $this->privacidad());
        return $this->renderFound($params, $repo->find(...), 'genealogy/grupo/show', 'grupo',
            fn(int $id) => ['dispersion' => $repo->dispersion($id)], self::movida($repo, null));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function red(array $params = []): string
    {
        $repo = new GrupoRepository($this->db(), $this->privacidad());
        return $this->renderFound($params, $repo->find(...), 'genealogy/grupo/red', 'grupo',
            fn(int $id) => ['personas' => $repo->red($id)], self::movida($repo, 1));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function dispersion(array $params = []): string
    {
        $repo = new GrupoRepository($this->db(), $this->privacidad());
        return $this->renderFound($params, $repo->find(...), 'genealogy/grupo/dispersion', 'grupo',
            fn(int $id) => ['dispersion' => $repo->dispersion($id)], self::movida($repo, 2));
    }
}
