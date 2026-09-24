<?php

namespace App\Controllers\POS;

use App\Controllers\BaseController;

/**
 * Producto — identificador numerico de 8 digitos.
 * El largo del segmento (8) es lo que hace que el router llegue aqui.
 */
class ProductoController extends BaseController
{
    public function show($params = [])
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid product ID');
        }

        // TODO: fetch from DB by $id
        $producto = [];

        return view('pos/producto/show', ['producto' => $producto]);
    }

    public function variantes($params = [])
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid product ID');
        }

        $variantes = [];

        return view('pos/producto/variantes', [
            'id' => $id,
            'variantes' => $variantes,
        ]);
    }

    public function atributos($params = [])
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid product ID');
        }

        $atributos = [];

        return view('pos/producto/atributos', [
            'id' => $id,
            'atributos' => $atributos,
        ]);
    }
}
