<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;

/**
 * Coleccion (arbol) — identificador numerico de 7 digitos.
 * TODO: cargar el recurso desde la base de datos por $id.
 */
class ColeccionController extends BaseController
{
    public function show(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/coleccion/show', 'coleccion');
    }

    public function vista(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/coleccion/vista', 'coleccion');
    }

    public function editar(array $params = []): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized();
        }

        return $this->renderById($params, 'genealogy/coleccion/editar', 'coleccion');
    }

    public function exportar(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/coleccion/exportar', 'coleccion');
    }
}
