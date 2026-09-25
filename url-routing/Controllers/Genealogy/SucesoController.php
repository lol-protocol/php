<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;

/**
 * Suceso — identificador numerico de 9 digitos.
 * TODO: cargar el recurso desde la base de datos por $id.
 */
class SucesoController extends BaseController
{
    public function show(array $params = []): string
    {
        return $this->renderById($params, 'genealogy/suceso/show', 'suceso');
    }
}
