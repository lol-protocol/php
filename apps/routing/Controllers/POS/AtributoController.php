<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;

/**
 * Atributo (color, talla...) — identificador numerico de 5 digitos.
 * TODO: cargar el recurso desde la base de datos por $id.
 */
class AtributoController extends BaseController
{
    public function show(array $params = []): string
    {
        return $this->renderById($params, 'pos/atributo/show', 'atributo');
    }

    public function productos(array $params = []): string
    {
        return $this->renderById($params, 'pos/atributo/productos', 'atributo');
    }
}
