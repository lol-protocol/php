<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;

/**
 * Registro documental — identificador numerico de 8 digitos.
 * TODO: cargar el recurso desde la base de datos por $id.
 */
class RegistroController extends BaseController
{
    public function show(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/registro/show', 'registro');
    }

    public function fuente(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/registro/fuente', 'registro');
    }
}
