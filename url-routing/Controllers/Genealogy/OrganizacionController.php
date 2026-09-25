<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;

/**
 * Organizacion — identificador numerico de 5 digitos.
 * TODO: cargar el recurso desde la base de datos por $id.
 */
class OrganizacionController extends BaseController
{
    public function show(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/organizacion/show', 'organizacion');
    }

    public function miembros(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/organizacion/miembros', 'organizacion');
    }

    public function registros(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/organizacion/registros', 'organizacion');
    }
}
