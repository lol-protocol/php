<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\ColeccionRepository;
use App\Support\GedcomExporter;

/** Coleccion (family tree) — 7-digit id. There is no login: every tree is reachable and editable. */
class ColeccionController extends BaseController
{
    private function repo(): ColeccionRepository
    {
        return new ColeccionRepository($this->db());
    }

    public function show(array $params = []): string
    {
        return $this->renderFound($params, $this->repo()->find(...), 'genealogy/coleccion/show', 'coleccion',
            fn(int $id) => ['personas' => $this->repo()->personas($id)]);
    }

    public function vista(array $params = []): string
    {
        return $this->renderFound($params, $this->repo()->find(...), 'genealogy/coleccion/vista', 'coleccion',
            fn(int $id) => ['personas' => $this->repo()->personas($id)]);
    }

    public function editar(array $params = []): string
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Identificador inválido');
        }

        $coleccion = $this->repo()->find((int)$id);
        if ($coleccion === null) {
            return $this->handleNotFound();
        }

        return view('genealogy/coleccion/editar', [
            'coleccion' => $coleccion,
            'personas' => $this->repo()->personas((int)$id),
        ]);
    }

    /** Downloads the tree as GEDCOM 5.5.1. */
    public function exportar(array $params = []): string
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Identificador inválido');
        }

        $coleccion = $this->repo()->find((int)$id);
        if ($coleccion === null) {
            return $this->handleNotFound();
        }

        $gedcom = (new GedcomExporter())->export($this->repo()->personasParaExportar((int)$id), $coleccion['nombre']);

        header('Content-Type: text/vnd.familysearch.gedcom; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"coleccion-{$id}.ged\"");

        return $gedcom;
    }
}
