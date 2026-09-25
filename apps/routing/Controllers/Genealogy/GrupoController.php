<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;

/**
 * Grupo (apellido) — identificador numerico de 6 digitos.
 * TODO: cargar el recurso desde la base de datos por $id.
 */
class GrupoController extends BaseController
{
    public function show(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/grupo/show', 'grupo');
    }

    public function red(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/grupo/red', 'grupo');
    }

    public function dispersion(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/grupo/dispersion', 'grupo');
    }
}
