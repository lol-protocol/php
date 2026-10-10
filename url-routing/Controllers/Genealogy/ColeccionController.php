<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\ColeccionRepository;
use App\Support\GedcomExporter;

/**
 * Coleccion (family tree) — 7-digit id. A public tree is open to anyone; a
 * private one (publica = false) is for the owner only and answers 404 to
 * everyone else, so it cannot be told apart from an id that doesn't exist. The
 * edit page is owner-only too; on a public tree, which is known to exist, it
 * asks for the token instead.
 */
class ColeccionController extends BaseController
{
    private function repo(): ColeccionRepository
    {
        return new ColeccionRepository($this->db(), $this->privacidad());
    }

    /**
     * The tree the request asks for, or the response that refuses it: 400 for
     * a bad id, 404 when missing, and, when the tree is private (or
     * $soloPropietario) and the request isn't the owner's, a 404 for a private
     * tree or a 401 for the edit page of a public one.
     *
     * @return array|string the colección row, or the response to send instead
     */
    private function accesible(array $params, bool $soloPropietario = false): array|string
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Identificador inválido');
        }

        $coleccion = $this->repo()->find((int)$id);
        if ($coleccion === null) {
            return $this->handleNotFound();
        }

        if ($soloPropietario || !$coleccion['publica']) {
            $denegado = $this->exigirPropietario(ocultar: !$coleccion['publica']);
            if ($denegado !== null) {
                return $denegado;
            }
        }

        return $coleccion;
    }

    public function show(array $params = []): string
    {
        return $this->renderAccesible($params, 'genealogy/coleccion/show');
    }

    public function vista(array $params = []): string
    {
        return $this->renderAccesible($params, 'genealogy/coleccion/vista');
    }

    public function editar(array $params = []): string
    {
        return $this->renderAccesible($params, 'genealogy/coleccion/editar', soloPropietario: true);
    }

    private function renderAccesible(array $params, string $view, bool $soloPropietario = false): string
    {
        $coleccion = $this->accesible($params, $soloPropietario);
        if (is_string($coleccion)) {
            return $coleccion;
        }

        return view($view, [
            'coleccion' => $coleccion,
            'personas' => $this->repo()->personas((int)$coleccion['id']),
        ]);
    }

    /** Downloads the tree as GEDCOM 5.5.1. */
    public function exportar(array $params = []): string
    {
        $coleccion = $this->accesible($params);
        if (is_string($coleccion)) {
            return $coleccion;
        }

        $id = (int)$coleccion['id'];
        $gedcom = (new GedcomExporter())->export($this->repo()->personasParaExportar($id), $coleccion['nombre']);

        header('Content-Type: text/vnd.familysearch.gedcom; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"coleccion-{$id}.ged\"");

        return $gedcom;
    }
}
