<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;

/**
 * Coleccion de productos — identificador numerico de 7 digitos.
 * TODO: cargar el recurso desde la base de datos por $id.
 */
class ColeccionController extends BaseController
{
    public function show(array $params = []): string
    {
        return $this->renderById($params, 'pos/coleccion/show', 'coleccion');
    }
}
