<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\ColeccionRepository;
use App\Support\GedcomExporter;

/**
 * Coleccion (family tree) — 7-digit id. A private tree is only visible to
 * its owner; anyone else gets a 404, not a 403, so its existence isn't
 * revealed.
 */
class ColeccionController extends BaseController
{
    private function repo(): ColeccionRepository
    {
        return new ColeccionRepository($this->db());
    }

    private function visible(int $id): ?array
    {
        $coleccion = $this->repo()->find($id);
        if ($coleccion === null || (!$coleccion['publica'] && !$this->isOwner($coleccion['usuario_id']))) {
            return null;
        }
        return $coleccion;
    }

    public function show(array $params = []): string
    {
        return $this->renderFound($params, $this->visible(...), 'genealogy/coleccion/show', 'coleccion',
            fn(int $id) => ['personas' => $this->repo()->personas($id)]);
    }

    public function vista(array $params = []): string
    {
        return $this->renderFound($params, $this->visible(...), 'genealogy/coleccion/vista', 'coleccion',
            fn(int $id) => ['personas' => $this->repo()->personas($id)]);
    }

    public function editar(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized();
        }

        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Identificador inválido');
        }

        $coleccion = $this->visible((int)$id);
        if ($coleccion === null) {
            return $this->handleNotFound();
        }
        if (!$this->isOwner($coleccion['usuario_id'])) {
            return $this->handleForbidden('Solo quien creó la colección puede editarla');
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

        $coleccion = $this->visible((int)$id);
        if ($coleccion === null) {
            return $this->handleNotFound();
        }

        $gedcom = (new GedcomExporter())->export($this->repo()->personasParaExportar((int)$id), $coleccion['nombre']);

        header('Content-Type: text/vnd.familysearch.gedcom; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"coleccion-{$id}.ged\"");

        return $gedcom;
    }
}
