<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\CatalogoRepository;

/** Grupo (category) — 4-digit id. */
class GrupoController extends BaseController
{
    /**
     * The category, with the filters its URL carries (/1001/48213/: the category narrowed to one atributo, and
     * up to one etiqueta more). A filter that does not exist is a 404 (never an empty page that looks like a
     * category with nothing in it), and the filters in a different order than the canonical one are a 301.
     *
     * @param array<string, mixed> $params
     */
    public function show(array $params = []): string
    {
        $repo = new CatalogoRepository($this->db());
        /** @var array<string, string> $filtros */
        $filtros = $params['filtros'] ?? [];

        if ($filtros === []) {
            return $this->renderFound($params, $repo->grupo(...), 'pos/grupo/show', 'grupo',
                fn(int $id) => ['subgrupos' => $repo->subgrupos($id), 'productos' => $repo->productosDeGrupo($id),
                    'filtros' => [], 'ofrecidos' => $repo->filtrosDeGrupo($id)]);
        }

        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Identificador inválido');
        }
        $grupo = $repo->grupo((int)$id);
        if ($grupo === null) {
            return $this->handleNotFound();
        }
        $aplicados = [];
        foreach ($filtros as $tipo => $filtro) {
            $fila = $tipo === 'atributo' ? $repo->atributo((int)$filtro) : $repo->etiqueta((int)$filtro);
            if ($fila === null) {
                return $this->handleNotFound();
            }
            $aplicados[$tipo] = $fila;
        }

        $canonica = filtrado('grupo', $id, $filtros);
        if ($canonica !== (string)strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) {
            return $this->redirect($canonica, 301);
        }

        return view('pos/grupo/show', [
            'grupo' => $grupo,
            'subgrupos' => [],
            'productos' => $repo->productosDeGrupo((int)$id, $filtros),
            'filtros' => $aplicados,
            'ofrecidos' => $repo->filtrosDeGrupo((int)$id),
        ]);
    }
}
