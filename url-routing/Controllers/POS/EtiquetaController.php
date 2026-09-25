<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;

/**
 * Etiqueta — identificador numerico de 6 digitos.
 * TODO: cargar el recurso desde la base de datos por $id.
 */
class EtiquetaController extends BaseController
{
    public function show(array $params = []): string
    {
        return $this->renderById($params, 'pos/etiqueta/show', 'etiqueta');
    }

    public function productos(array $params = []): string
    {
        return $this->renderById($params, 'pos/etiqueta/productos', 'etiqueta');
    }
}
