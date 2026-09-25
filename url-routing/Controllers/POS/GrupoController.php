<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;

/**
 * Grupo de productos — identificador numerico de 4 digitos.
 * TODO: cargar el recurso desde la base de datos por $id.
 */
class GrupoController extends BaseController
{
    public function show(array $params = []): string
    {
        return $this->renderById($params, 'pos/grupo/show', 'grupo');
    }
}
